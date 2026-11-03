import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Info, Warning } from '@phosphor-icons/react';
import Secret from '@/Components/Secret';
import { dateId, gram, percent, rupiah } from '@/lib/format';
import type { ZakatReference, ZakatView } from '@/types';

export function Disclaimer() {
    return (
        <p className="flex gap-2 rounded-xl border border-line bg-white p-3 text-sm leading-relaxed text-muted">
            <Info size={18} className="mt-0.5 shrink-0" aria-hidden />
            <span>Ini alat bantu hitung, bukan fatwa. Untuk kepastian, konsultasikan dengan lembaga zakat atau ulama yang Anda percaya.</span>
        </p>
    );
}

export function ReferenceList({ references }: { references: ZakatReference[] }) {
    if (references.length === 0) return <p className="text-sm text-muted">Rujukan tidak tersedia.</p>;
    return (
        <ul className="space-y-3 text-sm">
            {references.map((r) => (
                <li key={r.url + r.name}>
                    <a href={r.url} target="_blank" rel="noopener noreferrer" className="font-medium text-brand-dark underline underline-offset-2">{r.name}</a>
                    <span className="block text-xs text-muted">Diakses {dateId(r.accessed)}</span>
                    {r.note && <span className="mt-0.5 block text-muted">{r.note}</span>}
                </li>
            ))}
        </ul>
    );
}

// Decimal numbers and "Rp ..." amounts inside the engine's step text: shown in Indonesian format and masked by
// "Sembunyikan nominal". Dates and versions (digits joined by hyphens) are left alone.
const NUMBER = /Rp\s\d[\d.]*|(?<![\d-])\d+\.\d+(?![\d-])/g;

export function StepText({ text }: { text: string }) {
    const parts: ReactNode[] = [];
    let last = 0;
    for (const m of text.matchAll(NUMBER)) {
        const at = m.index ?? 0;
        parts.push(text.slice(last, at));
        parts.push(<Secret key={at}>{m[0].startsWith('Rp') ? m[0] : gram(m[0], 0)}</Secret>);
        last = at + m[0].length;
    }
    parts.push(text.slice(last));
    return <>{parts}</>;
}

/** The outcome of one calculation. Needs-review shows reasons and never an amount. */
export function ResultPanel({ view, editLinks = false }: { view: ZakatView; editLinks?: boolean }) {
    if (view.status === 'needs_review') {
        return (
            <section aria-label="Hasil" className="rounded-2xl border border-warn-line bg-warn-soft p-5">
                <h2 className="flex items-center gap-2 text-base font-semibold text-warn"><Warning size={20} aria-hidden />Perlu peninjauan</h2>
                <p className="mt-1 text-sm leading-relaxed">Perhitungan tidak memberi angka karena data kurang atau kasusnya belum didukung. Ini bukan nol.</p>
                <ul className="mt-3 divide-y divide-warn-line text-sm">
                    {view.reasons.map((r, i) => (
                        <li key={`${r.code}-${i}`} className="flex items-start gap-3 py-2">
                            <span className="min-w-0 flex-1">{r.message}</span>
                            {editLinks && r.item_id && (
                                <Link href={`/koleksi/${r.item_id}/edit`} className="inline-flex min-h-11 shrink-0 items-center font-semibold text-warn underline underline-offset-2">Lengkapi</Link>
                            )}
                        </li>
                    ))}
                </ul>
            </section>
        );
    }

    if (view.outcome === 'due') {
        return (
            <section aria-label="Hasil" className="rounded-2xl bg-brand-dark p-5 text-white sm:p-6">
                <h2 className="text-sm font-medium text-white/80">Wajib zakat. Zakat {view.madhhab_label}</h2>
                <p className="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">
                    <Secret>{gram(view.zakat_g)}</Secret> <span className="text-xl font-medium text-white/80 sm:text-2xl">g emas murni</span>
                </p>
                {view.zakat_rupiah !== null && (
                    <p className="mt-2 text-lg font-medium"><span className="text-white/80">Setara </span><Secret>{rupiah(view.zakat_rupiah)}</Secret></p>
                )}
                <p className="mt-3 max-w-[48ch] text-sm leading-relaxed text-white/85">
                    {percent(view.rate)}% dari <Secret>{gram(view.eligible_g)}</Secret> g emas murni yang dihitung. Haul {view.haul_start_hijri} H sampai {view.haul_end_hijri} H.
                </p>
            </section>
        );
    }

    return (
        <section aria-label="Hasil" className="panel p-5">
            <h2 className="text-base font-semibold">Belum wajib zakat</h2>
            {view.outcome === 'below_nisab' ? (
                <p className="mt-1 text-sm leading-relaxed">
                    Emas yang dihitung (<Secret>{gram(view.eligible_g)}</Secret> g) belum mencapai nisab {view.madhhab_label}, yaitu {gram(view.nisab_g, 0)} g emas murni.
                    Kurang <Secret>{gram(view.shortfall_g)}</Secret> g.
                </p>
            ) : (
                <p className="mt-1 text-sm leading-relaxed">
                    Nisab sudah tercapai, tetapi haul baru genap pada {dateId(view.haul_end)} ({view.haul_end_hijri} H), setelah tanggal penilaian.
                </p>
            )}
        </section>
    );
}

export function ContributionList({ view, linkItems = false }: { view: ZakatView; linkItems?: boolean }) {
    if (view.contributions.length === 0) return <p className="panel p-4 text-sm text-muted">Tidak ada barang yang tercatat saat perhitungan.</p>;
    return (
        <ul className="panel divide-y divide-line overflow-hidden">
            {view.contributions.map((c) => {
                const body = (
                    <>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate font-medium">{c.name}</span>
                            <span className="block text-sm text-muted">{c.included ? 'Ikut dihitung' : c.reason}</span>
                        </span>
                        <span className="shrink-0 text-right font-semibold">
                            {c.pure_gold_g === null ? <span className="text-sm font-medium text-warn">Data diperlukan</span> : <><Secret>{gram(c.pure_gold_g)}</Secret> g</>}
                        </span>
                    </>
                );
                return (
                    <li key={c.item_id}>
                        {linkItems && c.linkable ? (
                            <Link href={`/koleksi/${c.item_id}`} className="flex min-h-14 items-center gap-3 px-4 py-3 transition-colors hover:bg-paper">{body}</Link>
                        ) : (
                            <div className="flex min-h-14 items-center gap-3 px-4 py-3">{body}</div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
