import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { dateId, gram, purityLabel, rupiahCents } from '@/lib/format';
import type { ImportRow } from '@/types';

type Props = {
    token: string;
    filename: string;
    sheets: { name: string; count: number }[];
    sheet: string;
    rows: ImportRow[];
    counts: Partial<Record<ImportRow['status'], number>>;
};

const STATUS: Record<ImportRow['status'], { label: string; cls: string }> = {
    ok: { label: 'Baik', cls: 'bg-brand-soft text-brand-dark' },
    check: { label: 'Perlu dicek', cls: 'bg-warn-soft text-warn' },
    duplicate: { label: 'Sudah ada', cls: 'bg-paper text-muted border border-line' },
    error: { label: 'Galat', cls: 'bg-red-50 text-red-800' },
};

const TYPE_LABEL: Record<string, string> = { cincin: 'Cincin', kalung: 'Kalung', gelang: 'Gelang', anting: 'Anting', liontin: 'Liontin', lainnya: 'Lainnya' };

export default function ImporPratinjau({ token, filename, sheets, sheet, rows, counts }: Props) {
    // Each sheet starts from its own defaults: ok and check rows checked, duplicates and errors not.
    const [chosen, setChosen] = useState<Set<number>>(() => new Set(rows.filter((r) => r.selected).map((r) => r.row)));
    const [busy, setBusy] = useState(false);
    const importable = useMemo(() => rows.filter((r) => r.status !== 'error').map((r) => r.row), [rows]);

    const toggle = (n: number) => setChosen((s) => {
        const next = new Set(s);
        if (next.has(n)) next.delete(n); else next.add(n);
        return next;
    });

    const save = () => router.post(`/koleksi/impor/${token}`, { sheet, rows: [...chosen] }, { onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    const cancel = () => router.delete(`/koleksi/impor/${token}`);
    const switchSheet = (name: string) => router.get(`/koleksi/impor/${token}`, { sheet: name }, { preserveState: false });

    return (
        <AppLayout>
            <Head title="Pratinjau impor" />
            <Link href="/koleksi/impor" className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Impor dari Excel</Link>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Pratinjau impor</h1>
            <p className="mt-1 text-sm text-muted">Berkas <span translate="no" className="font-medium">{filename}</span>. Belum ada yang disimpan. Centang baris yang ingin diimpor.</p>

            {sheets.length > 1 && (
                <div role="tablist" aria-label="Sheet" className="mt-5 flex flex-wrap gap-2">
                    {sheets.map((s) => (
                        <button key={s.name} type="button" role="tab" aria-selected={s.name === sheet} onClick={() => switchSheet(s.name)}
                            className={`min-h-11 rounded-xl border px-3 text-sm font-medium ${s.name === sheet ? 'border-brand bg-brand-soft text-brand-dark' : 'border-line bg-white'}`}>
                            <span translate="no">{s.name}</span> ({s.count})
                        </button>
                    ))}
                </div>
            )}

            <p className="mt-4 flex flex-wrap gap-2 text-xs font-medium" aria-label="Ringkasan status">
                {(['ok', 'check', 'duplicate', 'error'] as const).filter((k) => counts[k]).map((k) => (
                    <span key={k} className={`rounded-md px-2 py-1 ${STATUS[k].cls}`}>{counts[k]} {STATUS[k].label.toLowerCase()}</span>
                ))}
            </p>

            <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" onClick={() => setChosen(new Set(rows.filter((r) => r.status === 'ok' || r.status === 'check').map((r) => r.row)))} className="btn btn-secondary">Pilih yang baik</button>
                <button type="button" onClick={() => setChosen(new Set(importable))} className="btn btn-secondary">Pilih semua</button>
                <button type="button" onClick={() => setChosen(new Set())} className="btn btn-secondary">Kosongkan</button>
            </div>

            <ul className="panel mt-4 divide-y divide-line overflow-hidden">
                {rows.map((r) => (
                    <li key={r.row}>
                        <label className={`flex gap-3 px-4 py-3 ${r.status === 'error' ? 'opacity-70' : 'cursor-pointer hover:bg-paper'}`}>
                            <input type="checkbox" checked={chosen.has(r.row)} disabled={r.status === 'error'} onChange={() => toggle(r.row)} className="mt-1 h-5 w-5 shrink-0 accent-[#1f6b52]" />
                            <span className="min-w-0 flex-1">
                                <span className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium" translate="no">{r.name}</span>
                                    <span className={`rounded-md px-1.5 py-0.5 text-xs font-semibold ${STATUS[r.status].cls}`}>{STATUS[r.status].label}</span>
                                </span>
                                <span className="mt-0.5 block text-sm text-muted">
                                    {[
                                        r.jewelry_type ? TYPE_LABEL[r.jewelry_type] : null,
                                        r.owned_since ? dateId(r.owned_since) : 'tanggal kosong',
                                        r.purity_source ? purityLabel(r.purity_source, r.purity_input) : 'kadar kosong',
                                        r.net_weight_g ? `${gram(r.net_weight_g)} g` : 'berat kosong',
                                        r.acquisition_cost ? rupiahCents(`${r.acquisition_cost}${r.acquisition_cost.includes('.') ? '' : '.00'}`) : 'harga kosong',
                                    ].filter(Boolean).join(' · ')}
                                </span>
                                {(r.store || r.resale_discount_percent) && (
                                    <span className="block text-xs text-muted" translate="no">
                                        {r.store ? `Dibeli di ${r.store}` : ''}{r.store && r.resale_discount_percent ? ' · ' : ''}{r.resale_discount_percent ? `Potongan ${gram(r.resale_discount_percent, 0)}%` : ''}
                                    </span>
                                )}
                                {r.notes.map((n) => <span key={n} className="mt-0.5 block text-xs leading-relaxed text-warn">{n}</span>)}
                                <span className="mt-0.5 block text-xs text-muted">Baris {r.row} di Excel</span>
                            </span>
                        </label>
                    </li>
                ))}
            </ul>

            <div className="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" onClick={cancel} className="btn btn-secondary">Batal</button>
                <button type="button" onClick={save} disabled={busy || chosen.size === 0} className="btn btn-primary">Simpan {chosen.size} barang</button>
            </div>
        </AppLayout>
    );
}
