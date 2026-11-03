<?php

namespace App\Services\Zakat;

use App\Services\GoldCalculator;
use InvalidArgumentException;

/**
 * Zakat on recorded gold for one madhhab. A pure function of ZakatInput and the rule sheet: no database, no clock.
 *
 * Returns a figure only when it can stand behind it. Missing data or a case the rule sheet does not cover gives
 * status needs_review with the reasons, never a guessed amount. All numbers are bcmath strings; the nisab is
 * compared on unrounded values and rounding is left to display.
 */
class ZakatCalculator
{
    private const SCALE = GoldCalculator::SCALE;

    public function __construct(private HijriCalendar $hijri = new HijriCalendar) {}

    /** @throws UnsupportedMadhhabException when the madhhab has no complete rule sheet */
    public function calculate(ZakatInput $input): ZakatResult
    {
        $rules = ZakatRuleSet::for($input->madhhab);
        $this->assertValid($input);

        $rule = "Aturan {$rules->label} (v{$rules->version})";
        $steps = [[
            'rule' => 'madhhab',
            'text' => "Memakai mazhab {$rules->label}, lembar aturan versi {$rules->version}.",
            'figures' => ['madhhab' => $rules->madhhab, 'version' => $rules->version, 'valuation_date' => $input->valuationDate],
            'reference' => $rule,
        ]];

        // 1. Which items count.
        [$contributions, $reasons] = $this->selectItems($input, $rules);
        $included = array_values(array_filter($contributions, fn (array $c) => $c['included']));

        foreach ($contributions as $c) {
            $steps[] = [
                'rule' => 'item',
                'text' => $c['included']
                    ? "{$c['name']}: ikut dihitung."
                    : "{$c['name']}: tidak dihitung. {$c['reason']}",
                'figures' => ['item_id' => $c['item_id'], 'pure_gold_g' => $c['pure_gold_g']],
                'reference' => $rule,
            ];
        }

        if ($reasons !== []) {
            return $this->review($input, $rules, $contributions, $reasons, $steps);
        }

        // 2. Eligible pure gold against the nisab (no rounding before this comparison).
        $eligible = '0';
        foreach ($included as $c) {
            $eligible = bcadd($eligible, $c['pure_gold_g'], self::SCALE);
        }
        $steps[] = [
            'rule' => 'eligible',
            'text' => 'Jumlah emas murni yang dihitung.',
            'figures' => ['eligible_g' => $eligible],
            'reference' => $rule,
        ];

        $steps[] = [
            'rule' => 'nisab',
            'text' => "Nisab {$rules->label}: {$rules->nisabG} g emas murni.",
            'figures' => ['nisab_g' => $rules->nisabG, 'eligible_g' => $eligible],
            'reference' => $rule,
        ];

        if (bccomp($eligible, $rules->nisabG, self::SCALE) < 0) {
            $shortfall = bcsub($rules->nisabG, $eligible, self::SCALE);
            $steps[] = [
                'rule' => 'below_nisab',
                'text' => 'Emas yang dihitung belum mencapai nisab, jadi belum wajib zakat.',
                'figures' => ['shortfall_g' => $shortfall],
                'reference' => $rule,
            ];

            return $this->build($input, $rules, ZakatResult::COMPLETE, ZakatResult::BELOW_NISAB, $contributions, $reasons, $steps,
                eligible: $eligible, shortfall: $shortfall);
        }

        // 3. Haul: when the nisab was reached, and whether a Hijri year has passed.
        [$haulStart, $haulReasons, $haulSteps] = $this->haulStart($input, $rules, $included);
        $steps = [...$steps, ...$haulSteps];

        if ($haulReasons !== []) {
            return $this->review($input, $rules, $contributions, $haulReasons, $steps);
        }

        $haulEnd = $this->hijri->addHijriYear($haulStart);
        $haul = [
            'haul_start' => $haulStart,
            'haul_end' => $haulEnd,
            'haul_start_hijri' => $this->hijri->format($haulStart),
            'haul_end_hijri' => $this->hijri->format($haulEnd),
        ];
        $steps[] = [
            'rule' => 'haul',
            'text' => "Haul: satu tahun Hijriah dari {$haul['haul_start_hijri']} H sampai {$haul['haul_end_hijri']} H (kalender ".HijriCalendar::METHOD.', selisih sampai ±'.HijriCalendar::TOLERANCE_DAYS.' hari antar metode).',
            'figures' => $haul,
            'reference' => $rule,
        ];
        $steps[] = [
            'rule' => 'assumption',
            'text' => 'Aplikasi hanya melihat koleksi yang tercatat saat ini. Emas yang sudah keluar (dijual atau diberikan) tidak tercatat, sehingga penurunan di bawah nisab di tengah haul tidak terdeteksi.',
            'figures' => [],
            'reference' => $rule,
        ];

        if ($input->valuationDate < $haulEnd) {
            $steps[] = [
                'rule' => 'haul_incomplete',
                'text' => "Haul baru genap pada {$haulEnd}, setelah tanggal penilaian, jadi belum wajib zakat.",
                'figures' => ['haul_end' => $haulEnd],
                'reference' => $rule,
            ];

            return $this->build($input, $rules, ZakatResult::COMPLETE, ZakatResult::HAUL_INCOMPLETE, $contributions, $reasons, $steps,
                eligible: $eligible, haul: $haul);
        }

        // 4. Amount.
        $zakatG = bcmul($eligible, $rules->rate, self::SCALE);
        $rupiah = $input->pricePerGram === null
            ? null
            : GoldCalculator::round(bcmul($zakatG, $input->pricePerGram, self::SCALE), 2);

        $steps[] = [
            'rule' => 'amount',
            'text' => "Zakat = {$rules->rate} x {$eligible} g.",
            'figures' => ['rate' => $rules->rate, 'eligible_g' => $eligible, 'zakat_g' => $zakatG],
            'reference' => $rule,
        ];
        if ($rupiah !== null) {
            $steps[] = [
                'rule' => 'price',
                'text' => "Dalam rupiah: {$zakatG} g x Rp {$input->pricePerGram} per gram.",
                'figures' => ['price_per_gram' => $input->pricePerGram, 'zakat_rupiah' => $rupiah],
                'reference' => 'Harga per gram diisi pemilik',
            ];
        }

        return $this->build($input, $rules, ZakatResult::COMPLETE, ZakatResult::DUE, $contributions, $reasons, $steps,
            eligible: $eligible, haul: $haul, zakatG: $zakatG, zakatRupiah: $rupiah);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, string>>}
     */
    private function selectItems(ZakatInput $input, ZakatRuleSet $rules): array
    {
        $contributions = [];
        $reasons = [];

        foreach ($input->items as $item) {
            $exclude = null;

            if ($item->ownership !== 'own') {
                $exclude = 'Titipan orang lain, tidak pernah dihitung.';
            } elseif ($item->date !== null && $item->dateCertainty === 'exact' && $item->date > $input->valuationDate) {
                $exclude = 'Dimiliki setelah tanggal penilaian.';
            } elseif ($item->kind === 'jewelry' && ! $rules->wornJewelryZakatable) {
                if ($item->purpose === 'worn') {
                    $exclude = "Perhiasan yang dipakai tidak dihitung menurut mazhab {$rules->label}.";
                } elseif ($item->purpose !== 'investment') {
                    $exclude = 'Tujuan perhiasan belum ditentukan.';
                    $reasons[] = [
                        'code' => 'jewelry_purpose_undecided',
                        'message' => "{$item->name}: tujuan perhiasan (dipakai atau simpanan) belum ditentukan, padahal mazhab {$rules->label} membedakannya.",
                        'item_id' => $item->id,
                    ];
                }
            }

            if ($exclude === null && $item->pureGold === null) {
                $reasons[] = [
                    'code' => 'item_needs_data',
                    'message' => "{$item->name}: emas murninya belum bisa dihitung (data kurang: ".implode(', ', $item->missing).').',
                    'item_id' => $item->id,
                ];
            }

            $contributions[] = [
                'item_id' => $item->id,
                'name' => $item->name,
                'kind' => $item->kind,
                'pure_gold_g' => $item->pureGold,
                'included' => $exclude === null,
                'reason' => $exclude,
            ];
        }

        return [$contributions, $reasons];
    }

    /**
     * The date the nisab was reached and has been held since, from the manual start or the items' own dates.
     *
     * @param  list<array<string, mixed>>  $included
     * @return array{0: ?string, 1: list<array<string, string>>, 2: list<array<string, mixed>>}
     */
    private function haulStart(ZakatInput $input, ZakatRuleSet $rules, array $included): array
    {
        $rule = "Aturan {$rules->label} (v{$rules->version})";
        $byId = [];
        foreach ($input->items as $item) {
            $byId[$item->id] = $item;
        }

        $reasons = [];
        $steps = [];

        if ($input->manualHaulStart !== null) {
            $start = $input->manualHaulStart;
            $steps[] = [
                'rule' => 'haul_start_manual',
                'text' => "Awal haul diisi pemilik: {$start}. Tanggal tiap barang tidak lagi menentukan awal haul; barang yang tanggalnya tidak diketahui dianggap sudah dimiliki sejak awal haul.",
                'figures' => ['haul_start' => $start],
                'reference' => 'Awal haul diisi pemilik',
            ];
        } else {
            $dated = [];
            foreach ($included as $c) {
                $item = $byId[$c['item_id']];
                if ($item->date === null || $item->dateCertainty !== 'exact') {
                    $reasons[] = [
                        'code' => 'date_uncertain',
                        'message' => "{$item->name}: tanggal mulai dimiliki perkiraan atau tidak diketahui, jadi awal haul tidak bisa dipastikan. Isi awal haul manual atau pastikan tanggalnya.",
                        'item_id' => $item->id,
                    ];
                } else {
                    $dated[] = [$item->date, $item->id, $c['pure_gold_g']];
                }
            }
            if ($reasons !== []) {
                return [null, $reasons, $steps];
            }

            usort($dated, fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $running = '0';
            $start = null;
            foreach ($dated as [$date, , $pure]) {
                $running = bcadd($running, $pure, self::SCALE);
                if (bccomp($running, $rules->nisabG, self::SCALE) >= 0) {
                    $start = $date;
                    break;
                }
            }

            $steps[] = [
                'rule' => 'haul_start_derived',
                'text' => "Awal haul: tanggal ketika emas yang dimiliki pertama kali mencapai nisab, yaitu {$start}.",
                'figures' => ['haul_start' => $start, 'nisab_g' => $rules->nisabG],
                'reference' => $rule,
            ];
        }

        // Gold added after the haul began: how it is treated is not verified for this sheet, so ask for review.
        foreach ($included as $c) {
            $item = $byId[$c['item_id']];
            $isAddition = $item->date !== null && $item->dateCertainty === 'exact' && $item->date > $start;
            if ($isAddition && $rules->additionsJoinHaul !== true) {
                $reasons[] = [
                    'code' => 'addition_during_haul',
                    'message' => "{$item->name}: ditambahkan pada {$item->date}, setelah awal haul ({$start}). Perlakuan emas tambahan di tengah haul untuk mazhab {$rules->label} belum diverifikasi.",
                    'item_id' => $item->id,
                ];
            }
        }

        if ($start > $input->valuationDate) {
            $reasons[] = [
                'code' => 'haul_start_after_valuation',
                'message' => 'Awal haul jatuh setelah tanggal penilaian.',
            ];
        }

        return [$start, $reasons, $steps];
    }

    private function assertValid(ZakatInput $input): void
    {
        // Throws on a malformed date.
        $this->hijri->toHijri($input->valuationDate);
        if ($input->manualHaulStart !== null) {
            $this->hijri->toHijri($input->manualHaulStart);
        }
        if ($input->pricePerGram !== null && (! is_numeric($input->pricePerGram) || bccomp($input->pricePerGram, '0', self::SCALE) <= 0)) {
            throw new InvalidArgumentException('Price per gram must be a positive number.');
        }
    }

    private function review(ZakatInput $input, ZakatRuleSet $rules, array $contributions, array $reasons, array $steps): ZakatResult
    {
        $steps[] = [
            'rule' => 'needs_review',
            'text' => 'Perlu peninjauan: perhitungan tidak memberi angka karena data kurang atau kasusnya belum didukung. Alasannya tercantum.',
            'figures' => ['reasons' => array_column($reasons, 'code')],
            'reference' => "Aturan {$rules->label} (v{$rules->version})",
        ];

        return $this->build($input, $rules, ZakatResult::NEEDS_REVIEW, null, $contributions, $reasons, $steps);
    }

    /**
     * @param  array<string, string>  $haul
     */
    private function build(
        ZakatInput $input,
        ZakatRuleSet $rules,
        string $status,
        ?string $outcome,
        array $contributions,
        array $reasons,
        array $steps,
        ?string $eligible = null,
        ?string $shortfall = null,
        array $haul = [],
        ?string $zakatG = null,
        ?string $zakatRupiah = null,
    ): ZakatResult {
        return new ZakatResult(
            status: $status,
            outcome: $outcome,
            madhhab: $rules->madhhab,
            ruleVersion: $rules->version,
            valuationDate: $input->valuationDate,
            nisabG: $rules->nisabG,
            rate: $rules->rate,
            eligibleG: $eligible,
            shortfallG: $shortfall,
            haulStart: $haul['haul_start'] ?? null,
            haulEnd: $haul['haul_end'] ?? null,
            haulStartHijri: $haul['haul_start_hijri'] ?? null,
            haulEndHijri: $haul['haul_end_hijri'] ?? null,
            pricePerGram: $input->pricePerGram,
            zakatG: $zakatG,
            zakatRupiah: $zakatRupiah,
            contributions: $contributions,
            reasons: $reasons,
            steps: $steps,
            references: $rules->references,
        );
    }
}
