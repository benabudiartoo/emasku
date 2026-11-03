import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import ExportButtons from '@/Components/ExportButtons';
import AppLayout from '@/Layouts/AppLayout';
import { dateId } from '@/lib/format';
import type { ZakatView } from '@/types';
import CaraHitungDrawer from './CaraHitungDrawer';
import { ContributionList, Disclaimer, ResultPanel } from './parts';

export default function Periode({ period }: { period: ZakatView }) {
    const [drawer, setDrawer] = useState(false);

    return (
        <AppLayout>
            <Head title={`Zakat ${dateId(period.valuation_date)}`} />
            <Link href="/zakat" className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Kembali</Link>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Zakat per {dateId(period.valuation_date)}</h1>
            <p className="mt-1 text-sm text-muted">Mazhab {period.madhhab_label} · aturan v{period.rule_version}. Salinan ini tidak berubah walau koleksi berubah.</p>

            <div className="mt-6 space-y-5">
                <ResultPanel view={period} />

                <div>
                    <h2 className="text-base font-semibold">Barang yang dihitung</h2>
                    <div className="mt-3"><ContributionList view={period} linkItems /></div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <button type="button" onClick={() => setDrawer(true)} className="btn btn-secondary">Lihat cara hitung</button>
                    <Link href="/zakat/hitung" className="btn btn-secondary">Hitung ulang</Link>
                </div>
                <ExportButtons base={`/ekspor/zakat/${period.id}`} label="perhitungan zakat ini" />
                <Disclaimer />
            </div>
            <CaraHitungDrawer open={drawer} onClose={() => setDrawer(false)} view={period} />
        </AppLayout>
    );
}
