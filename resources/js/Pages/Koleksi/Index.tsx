import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Diamond, MapPin, Plus, Stack, WarningCircle } from '@phosphor-icons/react';
import ExportButtons from '@/Components/ExportButtons';
import PilihJenisModal from '@/Components/PilihJenisModal';
import Secret from '@/Components/Secret';
import AppLayout from '@/Layouts/AppLayout';
import { gram, missingText, purityLabel, rupiahCents } from '@/lib/format';
import type { Item, Loc } from '@/types';

type Filters = { jenis?: string; lokasi?: string; data?: string; toko?: string };

export default function Index({ items, filters, locations, stores, has_any }: { items: Item[]; filters: Filters; locations: Loc[]; stores: string[]; has_any: boolean }) {
    const [pick, setPick] = useState(false);

    const setFilter = (key: keyof Filters, value: string) =>
        router.get('/koleksi', { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });

    const select = (key: keyof Filters, label: string, options: [string, string][]) => (
        <label className="block text-sm font-medium">{label}
            <select value={filters[key] ?? ''} onChange={(e) => setFilter(key, e.target.value)} className="field-input font-normal">
                {options.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
            </select>
        </label>
    );

    return (
        <AppLayout>
            <Head title="Koleksi Saya" />
            <header className="flex items-end justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Koleksi Saya</h1>
                    <p className="mt-1 text-sm text-muted">Semua emas yang Anda catat dan di mana tersimpannya.</p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <Link href="/koleksi/impor" className="btn btn-secondary">Impor Excel</Link>
                    <button type="button" onClick={() => setPick(true)} className="btn btn-primary hidden md:inline-flex"><Plus size={18} aria-hidden />Tambah</button>
                </div>
            </header>

            {has_any && (
                <section aria-label="Filter" className={`mt-6 grid gap-3 ${stores.length > 0 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3'}`}>
                    {select('jenis', 'Jenis', [['', 'Semua jenis'], ['bar', 'Batangan'], ['jewelry', 'Perhiasan']])}
                    {select('lokasi', 'Lokasi', [['', 'Semua lokasi'], ...locations.map((l): [string, string] => [l.id, l.name])])}
                    {select('data', 'Status data', [['', 'Semua status'], ['lengkap', 'Lengkap'], ['kurang', 'Perlu dilengkapi']])}
                    {stores.length > 0 && select('toko', 'Toko', [['', 'Semua toko'], ...stores.map((s): [string, string] => [s, s])])}
                </section>
            )}

            {has_any && <div className="mt-4"><ExportButtons base="/ekspor/koleksi" query={filters} label="koleksi" /></div>}

            {!has_any ? (
                <div className="panel mt-8 p-8 text-center">
                    <p className="font-medium">Belum ada emas yang dicatat.</p>
                    <Link href="/mulai" className="btn btn-primary mt-4">Tambah emas pertama</Link>
                </div>
            ) : items.length === 0 ? (
                <p className="mt-8 text-center text-sm text-muted">Tidak ada barang yang cocok dengan filter ini.</p>
            ) : (
                <ul className="mt-6 grid gap-3 sm:grid-cols-2">
                    {items.map((item) => (
                        <li key={item.id}>
                            <Link href={`/koleksi/${item.id}`} className="panel flex items-start gap-3 p-4 transition-colors hover:bg-paper">
                                {item.kind === 'bar' ? <Stack size={26} className="mt-0.5 shrink-0 text-brand" aria-hidden /> : <Diamond size={26} className="mt-0.5 shrink-0 text-brand" aria-hidden />}
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium">{item.name}</span>
                                    <span className="block text-sm text-muted">
                                        {item.kind === 'bar' ? `Batangan · ${purityLabel(item.purity_source, item.purity_input)} · ${item.pieces} keping` : `Perhiasan · ${purityLabel(item.purity_source, item.purity_input)}`}
                                    </span>
                                    <span className="mt-1 flex items-center gap-1 text-sm text-muted"><MapPin size={14} aria-hidden />{item.location?.name ?? 'Lokasi belum ditentukan'}</span>
                                    {item.purchase_store && <span className="mt-0.5 block truncate text-xs text-muted" translate="no">Dibeli di {item.purchase_store}</span>}
                                    <span className="mt-0.5 block text-xs text-muted">{item.acquisition_cost === null ? 'Harga beli belum diisi' : <>Harga beli <span className="font-medium text-ink"><Secret>{rupiahCents(item.acquisition_cost)}</Secret></span></>}</span>
                                    {item.ownership === 'held_for_others' && <span className="mt-1 block text-xs font-medium text-warn">Titipan{item.held_for ? ` · ${item.held_for}` : ''}, tidak dihitung</span>}
                                </span>
                                <span className="shrink-0 text-right">
                                    {item.status === 'complete' ? (
                                        <>
                                            <span className="block text-lg font-semibold"><Secret>{gram(item.pure_gold_g)}</Secret> g</span>
                                            <span className="block text-xs text-muted">emas murni{item.purity_estimate ? ' (perkiraan)' : ''}</span>
                                        </>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 rounded-lg border border-warn-line bg-warn-soft px-2 py-1 text-xs font-medium text-warn" title={missingText(item.missing)}>
                                            <WarningCircle size={14} aria-hidden />Data diperlukan
                                        </span>
                                    )}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {has_any && (
                <button type="button" onClick={() => setPick(true)} aria-label="Tambah emas"
                    className="fixed bottom-20 right-4 z-10 grid h-14 w-14 place-items-center rounded-2xl bg-brand text-white shadow-lg md:hidden"><Plus size={26} aria-hidden /></button>
            )}
            <PilihJenisModal open={pick} onClose={() => setPick(false)} />
        </AppLayout>
    );
}
