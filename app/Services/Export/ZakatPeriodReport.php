<?php

namespace App\Services\Export;

use App\Models\ZakatPeriod;
use App\Services\Zakat\ZakatPresenter;

/** One saved zakat calculation, built from the stored figures (never recomputed). */
class ZakatPeriodReport
{
    public function build(ZakatPeriod $period): Report
    {
        $v = ZakatPresenter::fromPeriod($period);
        $needsReview = $v['status'] === 'needs_review';

        $facts = [
            'Mazhab' => $v['madhhab_label'],
            'Tanggal penilaian' => Fmt::date($v['valuation_date']),
            'Hasil' => Fmt::zakatOutcome($v),
            'Nisab (g emas murni)' => Fmt::gram($v['nisab_g']),
            'Emas yang dihitung (g)' => Fmt::gram($v['eligible_g']),
        ];
        if ($v['shortfall_g'] !== null) {
            $facts['Kekurangan dari nisab (g)'] = Fmt::gram($v['shortfall_g']);
        }
        $facts += [
            'Awal haul' => $this->haul($v['haul_start'], $v['haul_start_hijri']),
            'Akhir haul' => $this->haul($v['haul_end'], $v['haul_end_hijri']),
            'Tarif' => Fmt::percent($v['rate']).'%',
            'Harga emas per gram' => Fmt::rupiah($v['price_per_gram'], 'Tidak diisi'),
            'Zakat (g emas murni)' => Fmt::zakatGrams($v),
            'Zakat (rupiah)' => Fmt::zakatRupiah($v),
            'Versi aturan' => $v['rule_version'],
        ];

        $sections = [
            new ReportSection('Barang yang dihitung', ['Nama', 'Jenis', 'Emas murni (g)', 'Status', 'Keterangan'], array_map(
                fn (array $c) => [
                    $c['name'],
                    Fmt::kind($c['kind']),
                    $c['pure_gold_g'] === null ? Fmt::NEEDS : Fmt::gram($c['pure_gold_g']),
                    $c['included'] ? 'Dihitung' : 'Tidak dihitung',
                    (string) ($c['reason'] ?? ''),
                ],
                $v['contributions'],
            ), 'Tidak ada barang.'),
        ];

        if ($needsReview) {
            $sections[] = new ReportSection('Alasan perlu peninjauan', ['Alasan'], array_map(fn (array $r) => [$r['message']], $v['reasons']));
        }

        $sections[] = new ReportSection('Langkah perhitungan', ['No', 'Langkah'], array_map(
            fn (array $s, int $i) => [(string) ($i + 1), Fmt::stepText($s['text'])],
            $v['steps'],
            array_keys($v['steps']),
        ), 'Tidak ada langkah tercatat.');

        $sections[] = new ReportSection('Rujukan', ['Sumber', 'Tautan', 'Diakses', 'Catatan'], array_map(
            fn (array $r) => [$r['name'], $r['url'], Fmt::date($r['accessed']), (string) ($r['note'] ?? '')],
            $v['references'],
        ), 'Rujukan tidak tersedia.');

        $notes = [Fmt::DISCLAIMER];
        if ($needsReview) {
            $notes[] = 'Perhitungan ini tidak memberi angka karena data kurang atau kasusnya belum didukung. Ini bukan nol.';
        }
        if ($v['rule_outdated']) {
            $notes[] = 'Aturan mazhab ini sudah berubah sejak perhitungan disimpan. Angka di atas adalah salinan saat itu; rujukan diambil dari aturan terkini.';
        }

        return new Report(
            title: 'Zakat '.$v['madhhab_label'].' - '.Fmt::date($v['valuation_date']),
            filename: 'zakat-'.$v['madhhab'].'-'.$v['valuation_date'],
            facts: $facts,
            sections: $sections,
            notes: $notes,
        );
    }

    private function haul(?string $date, ?string $hijri): string
    {
        if ($date === null) {
            return 'Belum dapat ditentukan';
        }

        return Fmt::date($date).($hijri ? " ({$hijri})" : '');
    }
}
