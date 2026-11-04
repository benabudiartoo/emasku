<?php

namespace App\Services\Export;

use App\Models\Item;
use App\Services\GoldCalculator;
use App\Services\ItemPresenter;

/** The owner's collection list, narrowed by the same filters as the collection page. */
class CollectionReport
{
    private const FILTER_LABEL = [
        'jenis' => ['bar' => 'Batangan', 'jewelry' => 'Perhiasan'],
        'data' => ['lengkap' => 'Data lengkap', 'kurang' => 'Data kurang'],
    ];

    public function __construct(private GoldCalculator $calc) {}

    /** @param  array{jenis?: ?string, lokasi?: ?string, data?: ?string, toko?: ?string}  $filters */
    public function build(string $userId, array $filters): Report
    {
        $items = ItemPresenter::filtered($userId, $filters, $this->calc);

        // The total is summed from the unrounded values, then rounded once, like the summary page.
        $models = Item::where('user_id', $userId)->whereIn('id', $items->pluck('id'))->with('units')->get();
        $summary = $this->calc->summarize($models);

        $rows = $items->map(fn (array $i) => [
            $i['name'],
            Fmt::kind($i['kind']),
            $i['ownership'] === 'own' ? 'Milik sendiri' : 'Titipan'.($i['held_for'] ? ' ('.$i['held_for'].')' : ''),
            $i['kind'] === 'bar' ? (string) $i['pieces'] : '',
            $i['net_g'] === null ? Fmt::NEEDS : Fmt::gram($i['net_g']),
            Fmt::purity($i['purity_source'], $i['purity_input']).($i['purity_estimate'] ? ' (perkiraan)' : ''),
            $i['pure_gold_g'] === null ? Fmt::NEEDS : Fmt::gram($i['pure_gold_g']),
            trim((string) ($i['purchase_store'] ?? '')),
            $i['acquisition_cost'] === null ? '' : Fmt::rupiahCents($i['acquisition_cost']),
            $i['price_per_gram'] === null ? '' : Fmt::rupiahCents($i['price_per_gram']),
            $i['resale_discount_percent'] === null ? '' : Fmt::gram($i['resale_discount_percent'], 0).'%',
            $i['discounted_price'] === null ? '' : Fmt::rupiahCents($i['discounted_price']),
            $i['status'] === 'complete' ? 'Lengkap' : Fmt::NEEDS.': '.Fmt::missing($i['missing']),
            trim((string) ($i['notes'] ?? '')),
        ])->all();

        return new Report(
            title: 'Koleksi emas',
            filename: 'koleksi-emas-'.Fmt::today(),
            facts: [
                'Tanggal' => Fmt::date(Fmt::today()),
                'Filter' => $this->filterText($filters, $userId),
                'Jumlah barang' => (string) $items->count(),
                'Total emas murni milik sendiri (g)' => Fmt::gram(GoldCalculator::round($summary['pure_gold'], 4)),
                'Barang data kurang' => (string) $summary['incomplete'],
            ],
            sections: [
                new ReportSection('Daftar barang', [
                    'Nama', 'Jenis', 'Kepemilikan', 'Keping', 'Berat bersih (g)', 'Kadar', 'Emas murni (g)', 'Toko',
                    'Harga beli', 'Harga per gram', 'Potongan', 'Harga setelah potongan', 'Data', 'Catatan',
                ], $rows, 'Belum ada barang untuk filter ini.'),
                new ReportSection('Rekap per toko', ['Toko', 'Jumlah barang', 'Total berat bersih (g)', 'Total harga beli', 'Harga per gram'], $this->perStore($models), 'Belum ada barang milik sendiri.'),
            ],
            notes: [
                'Total hanya menghitung barang milik sendiri yang datanya lengkap. Barang titipan dan barang dengan data kurang tidak ikut dihitung.',
                'Harga per gram adalah harga beli dibagi berat bersih. Harga setelah potongan adalah harga beli dikurangi potongan jual; ini perkiraan, bukan harga toko emas.',
                'Rekap per toko mengelompokkan menurut tulisan toko yang sama persis. Berat dan harga hanya dijumlah dari barang yang beratnya diketahui; harga per gram dari barang yang punya harga beli dan berat.',
            ],
            landscape: true,
        );
    }

    /**
     * One row per store (grouped by the exact trimmed text), own items only. Weights and costs are summed unrounded and
     * rounded once; an item without a known weight adds to the count but not to the weight, and the price per gram only
     * uses items that have both a cost and a weight, so the figures stay consistent.
     *
     * @param  iterable<Item>  $models  with `units` loaded
     * @return list<list<string>>
     */
    private function perStore(iterable $models): array
    {
        $groups = [];
        foreach ($models as $item) {
            if ($item->ownership !== 'own') {
                continue;
            }
            $store = trim((string) $item->purchase_store) ?: 'Tanpa toko';
            $g = $groups[$store] ??= ['count' => 0, 'net' => '0', 'has_net' => false, 'cost' => '0', 'has_cost' => false, 'pair_net' => '0', 'pair_cost' => '0'];
            $g['count']++;

            $net = $this->calc->netWeight($item);
            if ($net->isComplete()) {
                $g['net'] = bcadd($g['net'], $net->value, GoldCalculator::SCALE);
                $g['has_net'] = true;
            }
            if ($item->acquisition_cost !== null) {
                $g['cost'] = bcadd($g['cost'], (string) $item->acquisition_cost, GoldCalculator::SCALE);
                $g['has_cost'] = true;
                if ($net->isComplete() && bccomp($net->value, '0', GoldCalculator::SCALE) > 0) {
                    $g['pair_net'] = bcadd($g['pair_net'], $net->value, GoldCalculator::SCALE);
                    $g['pair_cost'] = bcadd($g['pair_cost'], (string) $item->acquisition_cost, GoldCalculator::SCALE);
                }
            }
            $groups[$store] = $g;
        }

        // Alphabetical, with the items that have no store last.
        uksort($groups, fn (string $a, string $b) => ($a === 'Tanpa toko') <=> ($b === 'Tanpa toko') ?: strcasecmp($a, $b));

        $rows = [];
        foreach ($groups as $store => $g) {
            $rows[] = [
                (string) $store,
                (string) $g['count'],
                $g['has_net'] ? Fmt::gram(GoldCalculator::round($g['net'], 4)) : Fmt::NEEDS,
                $g['has_cost'] ? Fmt::rupiahCents(GoldCalculator::round($g['cost'], 2)) : '',
                bccomp($g['pair_net'], '0', GoldCalculator::SCALE) > 0
                    ? Fmt::rupiahCents(GoldCalculator::round(bcdiv($g['pair_cost'], $g['pair_net'], GoldCalculator::SCALE), 2))
                    : '',
            ];
        }

        return $rows;
    }

    private function filterText(array $filters, string $userId): string
    {
        $parts = [];
        foreach (['jenis', 'data'] as $key) {
            if (($filters[$key] ?? null) && isset(self::FILTER_LABEL[$key][$filters[$key]])) {
                $parts[] = self::FILTER_LABEL[$key][$filters[$key]];
            }
        }
        if ($filters['toko'] ?? null) {
            $parts[] = 'Toko '.$filters['toko'];
        }
        if ($filters['lokasi'] ?? null) {
            $name = \App\Models\Location::where('user_id', $userId)->whereKey($filters['lokasi'])->value('name');
            if ($name) {
                $parts[] = 'Lokasi '.$name;
            }
        }

        return $parts === [] ? 'Semua barang' : implode(', ', $parts);
    }
}
