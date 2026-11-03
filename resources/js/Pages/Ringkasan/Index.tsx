import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { CaretRight, Plus, Warning } from '@phosphor-icons/react';
import PilihJenisModal from '@/Components/PilihJenisModal';
import Secret from '@/Components/Secret';
import AppLayout from '@/Layouts/AppLayout';
import { dateId, gram, missingText } from '@/lib/format';
import type { ZakatSummary } from '@/types';

type Props = {
    has_any: boolean;
    active_count: number;
    bars: number;
    jewelry: number;
    pure_gold_g: string;
    complete: number;
    incomplete: { id: string; name: string; missing: string[] }[];
    today: string;
    zakat: ZakatSummary | null;
};

/** The latest saved zakat calculation in one line; amounts follow "Sembunyikan nominal". */
function ZakatState({ zakat }: { zakat: ZakatSummary | null }) {
    if (!zakat) return <span className="text-muted">Belum dihitung</span>;
    if (zakat.status === 'needs_review') return <span className="text-warn">Perlu peninjauan</span>;
    if (zakat.outcome === 'due') return <><Secret>{gram(zakat.zakat_g)}</Secret> g</>;
    return <span className="text-muted">{zakat.outcome === 'below_nisab' ? 'Belum mencapai nisab' : 'Haul belum genap'}</span>;
}

export default function Index({ has_any, active_count, bars, jewelry, pure_gold_g, complete, incomplete, today, zakat }: Props) {
    const [pick, setPick] = useState(false);

    return (
        <AppLayout>
            <Head title="Ringkasan" />
            <header className="flex items-end justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Ringkasan</h1>
                    <p className="mt-1 text-sm text-muted">Per {dateId(today)}</p>
                </div>
                {has_any && <button type="button" onClick={() => setPick(true)} className="btn btn-primary hidden md:inline-flex"><Plus size={18} aria-hidden />Tambah emas</button>}
            </header>

            {!has_any ? (
                <div className="panel mt-8 p-8 text-center">
                    <p className="font-medium">Belum ada emas yang dicatat.</p>
                    <p className="mt-1 text-sm text-muted">Mulai dengan satu barang, atau catat koleksi lama Anda.</p>
                    <Link href="/mulai" className="btn btn-primary mt-4">Mulai catat</Link>
                </div>
            ) : (
                <div className="mt-6 grid gap-6 lg:grid-cols-12 lg:gap-8">
                    <div className="space-y-6 lg:col-span-7">
                        <section aria-labelledby="emas-murni-title" className="rounded-2xl bg-brand-dark p-5 text-white sm:p-6">
                            <h2 id="emas-murni-title" className="text-sm font-medium text-white/80">Total emas murni</h2>
                            <p className="mt-2 text-5xl font-semibold tracking-tight sm:text-6xl">
                                <Secret>{gram(pure_gold_g)}</Secret> <span className="text-2xl font-medium text-white/80 sm:text-3xl">g</span>
                            </p>
                            <p className="mt-3 max-w-[44ch] text-sm leading-relaxed text-white/85">
                                Dari {complete} dari {active_count} barang aktif.{incomplete.length > 0 && ` ${incomplete.length} barang belum bisa dihitung karena datanya kurang.`}
                            </p>
                            <Link href="/ringkasan/rincian/emas-murni" className="mt-5 inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-white/30 px-4 text-sm font-medium transition-colors hover:bg-white/10">
                                Lihat barang penyusun<CaretRight size={16} aria-hidden />
                            </Link>
                        </section>

                        {incomplete.length > 0 && (
                            <section aria-labelledby="peringatan-title" className="flex gap-3 rounded-2xl border border-warn-line bg-warn-soft p-4">
                                <Warning size={24} className="shrink-0 text-warn" aria-hidden />
                                <div className="min-w-0">
                                    <h2 id="peringatan-title" className="text-sm font-semibold text-warn">{incomplete.length} barang perlu dilengkapi</h2>
                                    <ul className="mt-1 space-y-1 text-sm leading-relaxed">
                                        {incomplete.slice(0, 3).map((i) => <li key={i.id}><span translate="no">{i.name}</span>: {missingText(i.missing)} belum diisi.</li>)}
                                    </ul>
                                    <p className="mt-1 text-sm">Ini bukan nol, hanya belum bisa dihitung, jadi belum masuk total.</p>
                                    <Link href="/koleksi?data=kurang" className="mt-2 inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-warn underline underline-offset-2">Lengkapi data</Link>
                                </div>
                            </section>
                        )}
                    </div>

                    <section aria-label="Angka ringkasan" className="lg:col-span-5">
                        <ul className="panel divide-y divide-line overflow-hidden">
                            <li>
                                <Link href="/ringkasan/rincian/barang" className="flex min-h-14 items-center gap-3 px-4 py-3 transition-colors hover:bg-paper">
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm text-muted">Barang aktif</span>
                                        <span className="block text-lg font-semibold"><Secret>{active_count}</Secret> barang</span>
                                    </span>
                                    <span className="hidden text-sm text-muted sm:block">{bars} batangan · {jewelry} perhiasan</span>
                                    <CaretRight size={18} className="text-muted" aria-hidden />
                                </Link>
                            </li>
                            <li>
                                <Link href="/zakat" className="flex min-h-14 items-center gap-3 px-4 py-3 transition-colors hover:bg-paper">
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm text-muted">Status zakat</span>
                                        <span className="block text-base font-medium"><ZakatState zakat={zakat} /></span>
                                    </span>
                                    <CaretRight size={18} className="text-muted" aria-hidden />
                                </Link>
                            </li>
                        </ul>
                    </section>
                </div>
            )}

            {has_any && <button type="button" onClick={() => setPick(true)} className="btn btn-primary fixed bottom-20 right-4 z-10 h-12 px-5 shadow-lg md:hidden"><Plus size={18} aria-hidden />Tambah emas</button>}
            <PilihJenisModal open={pick} onClose={() => setPick(false)} />
        </AppLayout>
    );
}
