<?php

namespace App\Services\Export;

use App\Models\ZakatPeriod;
use App\Services\Zakat\ZakatPresenter;
use Illuminate\Support\Collection;

/** All of the owner's saved zakat calculations, newest first, from the stored figures. */
class ZakatListReport
{
    /** @param  Collection<int, ZakatPeriod>  $periods */
    public function build(Collection $periods): Report
    {
        $rows = $periods->map(function (ZakatPeriod $p) {
            $v = ZakatPresenter::fromPeriod($p);

            return [
                Fmt::date($v['valuation_date']),
                $v['madhhab_label'],
                Fmt::zakatOutcome($v),
                Fmt::gram($v['eligible_g'], 2, Fmt::NEEDS),
                Fmt::zakatGrams($v),
                Fmt::zakatRupiah($v),
            ];
        })->values()->all();

        return new Report(
            title: 'Perhitungan zakat',
            filename: 'zakat-'.Fmt::today(),
            facts: [
                'Tanggal' => Fmt::date(Fmt::today()),
                'Jumlah perhitungan' => (string) $periods->count(),
            ],
            sections: [new ReportSection('Perhitungan tersimpan', [
                'Tanggal penilaian', 'Mazhab', 'Hasil', 'Emas dihitung (g)', 'Zakat (g)', 'Zakat (Rp)',
            ], $rows, 'Belum ada perhitungan tersimpan.')],
            notes: [Fmt::DISCLAIMER],
        );
    }
}
