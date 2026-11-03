<?php

namespace App\Services\Export;

use App\Models\Item;
use App\Services\GoldCalculator;

/** The summary page as a document: counts, pure gold in total and per kind, and the items still missing data. */
class SummaryReport
{
    public function __construct(private GoldCalculator $calc) {}

    public function build(string $userId): Report
    {
        $items = Item::where('user_id', $userId)->with('units')->get();
        $summary = $this->calc->summarize($items);

        $owned = $items->where('ownership', 'own');
        $complete = $owned->filter(fn (Item $i) => $this->calc->pureGold($i)->isComplete());

        // Split by kind from the unrounded values, so the parts add up to the total.
        $pureOf = fn (string $kind) => GoldCalculator::round(
            $complete->where('kind', $kind)->reduce(fn (string $sum, Item $i) => bcadd($sum, $this->calc->pureGold($i)->value, GoldCalculator::SCALE), '0'),
            4,
        );

        $missing = array_map(
            fn (Item $i) => [$i->name, Fmt::missing($this->calc->pureGold($i)->missing)],
            $summary['incomplete_items'],
        );

        return new Report(
            title: 'Ringkasan emas',
            filename: 'ringkasan-emas-'.Fmt::today(),
            facts: [
                'Tanggal' => Fmt::date(Fmt::today()),
                'Barang aktif' => (string) $summary['counted'],
                'Total emas murni (g)' => Fmt::gram(GoldCalculator::round($summary['pure_gold'], 4)),
                'Barang data kurang' => (string) $summary['incomplete'],
            ],
            sections: [
                new ReportSection('Emas murni per jenis', ['Jenis', 'Jumlah barang', 'Emas murni (g)'], [
                    ['Batangan', (string) $owned->where('kind', 'bar')->count(), Fmt::gram($pureOf('bar'))],
                    ['Perhiasan', (string) $owned->where('kind', 'jewelry')->count(), Fmt::gram($pureOf('jewelry'))],
                ]),
                new ReportSection('Barang dengan data kurang', ['Nama', 'Data yang diperlukan'], $missing, 'Semua barang datanya lengkap.'),
            ],
            notes: [
                'Hanya barang milik sendiri yang dihitung. Barang titipan tidak ikut. Barang dengan data kurang tidak dihitung sebagai nol.',
            ],
        );
    }
}
