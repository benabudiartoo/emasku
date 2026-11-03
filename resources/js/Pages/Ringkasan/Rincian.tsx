import { Head, Link } from '@inertiajs/react';
import { CaretRight, Diamond, Stack, Warning } from '@phosphor-icons/react';
import Secret from '@/Components/Secret';
import AppLayout from '@/Layouts/AppLayout';
import { dateId, gram, missingText, purityLabel } from '@/lib/format';
import type { Item } from '@/types';

type Props = { metric: 'barang' | 'emas-murni'; total_g: string; bars_g: string; jewelry_g: string; items: Item[]; incomplete: Item[]; today: string };

export default function Rincian({ metric, total_g, bars_g, jewelry_g, items, incomplete, today }: Props) {
    const pure = metric === 'emas-murni';

    return (
        <AppLayout>
            <Head title={pure ? 'Barang penyusun emas murni' : 'Barang aktif'} />
            <Link href="/ringkasan" className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Kembali</Link>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{pure ? 'Barang penyusun emas murni' : 'Barang aktif'}</h1>
            <p className="mt-1 text-sm text-muted">Milik sendiri, per {dateId(today)}. Titipan orang lain tidak dihitung.</p>

            {pure && (
                <section aria-label="Total" className="mt-6 rounded-2xl bg-brand-dark p-5 text-white sm:p-6">
                    <h2 className="text-sm font-medium text-white/80">Total emas murni</h2>
                    <p className="mt-2 text-5xl font-semibold tracking-tight"><Secret>{gram(total_g)}</Secret> <span className="text-2xl font-medium text-white/80">g</span></p>
                    <dl className="mt-4 grid grid-cols-2 gap-4 border-t border-white/20 pt-4 text-sm">
                        <div><dt className="text-white/80">Batangan</dt><dd className="mt-0.5 text-lg font-semibold"><Secret>{gram(bars_g)}</Secret> g</dd></div>
                        <div><dt className="text-white/80">Perhiasan</dt><dd className="mt-0.5 text-lg font-semibold"><Secret>{gram(jewelry_g)}</Secret> g</dd></div>
                    </dl>
                </section>
            )}

            {pure && incomplete.length > 0 && (
                <section aria-labelledby="belum-title" className="mt-6 rounded-2xl border border-warn-line bg-warn-soft p-4">
                    <h2 id="belum-title" className="flex items-center gap-2 text-sm font-semibold text-warn"><Warning size={18} aria-hidden />Belum masuk hitungan</h2>
                    <ul className="mt-2 divide-y divide-warn-line">
                        {incomplete.map((i) => (
                            <li key={i.id} className="flex items-center gap-3 py-2">
                                <Link href={`/koleksi/${i.id}`} className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">{i.name}</span>
                                    <span className="block text-sm">Kurang: {missingText(i.missing)}</span>
                                </Link>
                                <Link href={`/koleksi/${i.id}/edit`} className="inline-flex min-h-11 items-center text-sm font-semibold text-warn underline underline-offset-2">Lengkapi</Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section aria-labelledby="barang-title" className="mt-8">
                <h2 id="barang-title" className="text-base font-semibold">{pure ? 'Barang yang dihitung' : 'Semua barang aktif'}</h2>
                {items.length === 0 ? (
                    <p className="panel mt-3 p-6 text-center text-sm text-muted">Belum ada barang.</p>
                ) : (
                    <ul className="panel mt-3 divide-y divide-line overflow-hidden">
                        {items.map((i) => (
                            <li key={i.id} className="flex items-center">
                                <Link href={`/koleksi/${i.id}`} className="flex min-h-14 min-w-0 flex-1 items-center gap-3 px-4 py-3 transition-colors hover:bg-paper">
                                    {i.kind === 'bar' ? <Stack size={24} className="shrink-0 text-brand" aria-hidden /> : <Diamond size={24} className="shrink-0 text-brand" aria-hidden />}
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-medium">{i.name}</span>
                                        <span className="block text-sm text-muted">{purityLabel(i.purity_source, i.purity_input)}{i.kind === 'bar' ? ` · ${i.pieces} keping` : ''}</span>
                                    </span>
                                    {i.status === 'complete' ? (
                                        <span className="shrink-0 text-right"><span className="block font-semibold"><Secret>{gram(i.pure_gold_g)}</Secret> g</span><span className="block text-xs text-muted">emas murni</span></span>
                                    ) : (
                                        <span className="shrink-0 rounded-lg border border-warn-line bg-warn-soft px-2 py-1 text-xs font-medium text-warn">Data diperlukan</span>
                                    )}
                                    <CaretRight size={18} className="shrink-0 text-muted" aria-hidden />
                                </Link>
                                {!pure && i.status !== 'complete' && (
                                    <Link href={`/koleksi/${i.id}/edit`} className="mr-3 inline-flex min-h-11 items-center text-sm font-semibold text-warn underline underline-offset-2">Lengkapi</Link>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}
