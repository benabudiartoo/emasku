import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { CaretRight, Scales, Warning } from '@phosphor-icons/react';
import ExportButtons from '@/Components/ExportButtons';
import Secret from '@/Components/Secret';
import AppLayout from '@/Layouts/AppLayout';
import { dateId, gram, rupiah } from '@/lib/format';
import type { ZakatMadhhab, ZakatSummary } from '@/types';
import PenjelasanMazhabModal from './PenjelasanMazhabModal';
import { Disclaimer } from './parts';

/** One line describing a stored period's state; amounts are masked by "Sembunyikan nominal". */
function StateText({ p }: { p: ZakatSummary }) {
    if (p.status === 'needs_review') return <span className="inline-flex items-center gap-1 font-medium text-warn"><Warning size={16} aria-hidden />Perlu peninjauan, belum ada angka</span>;
    if (p.outcome === 'due') {
        return <span className="font-medium"><Secret>{gram(p.zakat_g)}</Secret> g{p.zakat_rupiah !== null && <> · <Secret>{rupiah(p.zakat_rupiah)}</Secret></>}</span>;
    }
    return <span className="text-muted">{p.outcome === 'below_nisab' ? 'Belum mencapai nisab' : 'Haul belum genap'}</span>;
}

export default function Index({ periods, madhhabs }: { periods: ZakatSummary[]; madhhabs: ZakatMadhhab[] }) {
    const [about, setAbout] = useState(false);
    const latest = periods[0];

    return (
        <AppLayout>
            <Head title="Zakat" />
            <header className="flex items-end justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Zakat</h1>
                    <p className="mt-1 text-sm text-muted">Hitung zakat emas Anda menurut mazhab yang dipilih.</p>
                </div>
                <Link href="/zakat/hitung" className="btn btn-primary"><Scales size={18} aria-hidden />Hitung zakat</Link>
            </header>

            {!latest ? (
                <div className="panel mt-8 p-8 text-center">
                    <p className="font-medium">Belum ada perhitungan zakat.</p>
                    <p className="mt-1 text-sm text-muted">Pilih tanggal dan mazhab, lalu lihat berapa zakatnya.</p>
                    <Link href="/zakat/hitung" className="btn btn-primary mt-4">Hitung zakat pertama</Link>
                </div>
            ) : (
                <>
                    <Link href={`/zakat/periode/${latest.id}`} aria-label="Perhitungan terakhir"
                        className={`mt-6 flex items-center gap-3 rounded-2xl border p-5 transition-colors ${latest.status === 'needs_review' ? 'border-warn-line bg-warn-soft' : 'border-line bg-white hover:bg-paper'}`}>
                        <span className="min-w-0 flex-1">
                            <span className="block text-sm text-muted">Perhitungan terakhir · {latest.madhhab_label} · {dateId(latest.valuation_date)}</span>
                            <span className="mt-1 block text-lg"><StateText p={latest} /></span>
                        </span>
                        <CaretRight size={18} className="shrink-0 text-muted" aria-hidden />
                    </Link>

                    <section aria-labelledby="periode-title" className="mt-8">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 id="periode-title" className="text-base font-semibold">Perhitungan tersimpan</h2>
                            <ExportButtons base="/ekspor/zakat" label="daftar zakat" />
                        </div>
                        <ul className="panel mt-3 divide-y divide-line overflow-hidden">
                            {periods.map((p) => (
                                <li key={p.id}>
                                    <Link href={`/zakat/periode/${p.id}`} className="flex min-h-14 items-center gap-3 px-4 py-3 transition-colors hover:bg-paper">
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-medium">{dateId(p.valuation_date)} · {p.madhhab_label}</span>
                                            <span className="block text-sm"><StateText p={p} /></span>
                                        </span>
                                        <CaretRight size={18} className="shrink-0 text-muted" aria-hidden />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                </>
            )}

            <div className="mt-8 space-y-3">
                <Disclaimer />
                <button type="button" onClick={() => setAbout(true)} className="inline-flex min-h-11 items-center text-sm font-semibold text-brand-dark underline underline-offset-2">Tentang mazhab dan rujukan</button>
            </div>
            <PenjelasanMazhabModal open={about} onClose={() => setAbout(false)} madhhabs={madhhabs} />
        </AppLayout>
    );
}
