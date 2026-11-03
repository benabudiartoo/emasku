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

    /** @param  array{jenis?: ?string, lokasi?: ?string, data?: ?string}  $filters */
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
            $i['location']['name'] ?? '',
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
            sections: [new ReportSection('Daftar barang', [
                'Nama', 'Jenis', 'Kepemilikan', 'Keping', 'Berat bersih (g)', 'Kadar', 'Emas murni (g)', 'Lokasi', 'Data', 'Catatan',
            ], $rows, 'Belum ada barang untuk filter ini.')],
            notes: [
                'Total hanya menghitung barang milik sendiri yang datanya lengkap. Barang titipan dan barang dengan data kurang tidak ikut dihitung.',
            ],
        );
    }

    private function filterText(array $filters, string $userId): string
    {
        $parts = [];
        foreach (['jenis', 'data'] as $key) {
            if (($filters[$key] ?? null) && isset(self::FILTER_LABEL[$key][$filters[$key]])) {
                $parts[] = self::FILTER_LABEL[$key][$filters[$key]];
            }
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
