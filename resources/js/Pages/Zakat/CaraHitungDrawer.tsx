import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { X } from '@phosphor-icons/react';
import Secret from '@/Components/Secret';
import { dateId, gram, percent, rupiah } from '@/lib/format';
import type { ZakatView } from '@/types';
import { Disclaimer, ReferenceList, StepText } from './parts';

/** Bottom drawer with every step of a calculation and the references behind it. X or backdrop closes it. */
export default function CaraHitungDrawer({ open, onClose, view }: { open: boolean; onClose: () => void; view: ZakatView }) {
    const ref = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const d = ref.current;
        if (!d) return;
        if (open && !d.open) d.showModal();
        if (!open && d.open) d.close();
    }, [open]);

    const row = (label: string, value: ReactNode) => (
        <div className="flex justify-between gap-4 py-2 text-sm"><dt className="text-muted">{label}</dt><dd className="text-right font-medium">{value}</dd></div>
    );

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(e) => { if (e.target === ref.current) onClose(); }}
            aria-label="Cara hitung"
            className="fixed bottom-0 left-0 right-0 top-auto m-0 max-h-[85vh] w-full max-w-none overflow-y-auto rounded-t-2xl border border-line bg-white p-0 text-ink shadow-xl backdrop:bg-ink/45 sm:left-1/2 sm:max-w-xl sm:-translate-x-1/2"
        >
            <div className="sticky top-0 flex items-center justify-between border-b border-line bg-white px-5 py-3">
                <h2 className="text-base font-semibold">Cara hitung</h2>
                <button type="button" onClick={onClose} aria-label="Tutup" className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-muted hover:bg-paper"><X size={20} aria-hidden /></button>
            </div>
            {open && (
                <div className="space-y-5 p-5">
                    <dl className="divide-y divide-line">
                        {row('Tanggal penilaian', dateId(view.valuation_date))}
                        {row('Mazhab', `${view.madhhab_label} (aturan v${view.rule_version})`)}
                        {row('Nisab', <><Secret>{gram(view.nisab_g, 0)}</Secret> g emas murni</>)}
                        {row('Tarif', `${percent(view.rate)}%`)}
                        {view.haul_start && row('Haul', `${view.haul_start_hijri} H sampai ${view.haul_end_hijri} H`)}
                        {view.price_per_gram !== null && row('Harga per gram', <Secret>{rupiah(view.price_per_gram)}</Secret>)}
                        {view.eligible_g !== null && row('Emas yang dihitung', <><Secret>{gram(view.eligible_g)}</Secret> g</>)}
                        {view.zakat_g !== null && row('Zakat', <><Secret>{gram(view.zakat_g)}</Secret> g</>)}
                        {view.zakat_rupiah !== null && row('Zakat (rupiah)', <Secret>{rupiah(view.zakat_rupiah)}</Secret>)}
                    </dl>

                    {view.rule_outdated && (
                        <p className="rounded-xl border border-warn-line bg-warn-soft p-3 text-sm">
                            Aturan {view.madhhab_label} sudah diperbarui sejak perhitungan ini dibuat. Rujukan di bawah adalah aturan terbaru; angka di atas tetap seperti saat dihitung.
                        </p>
                    )}

                    <section aria-labelledby="langkah-title">
                        <h3 id="langkah-title" className="font-semibold">Langkah</h3>
                        <ol className="mt-2 list-decimal space-y-2 pl-5 text-sm leading-relaxed">
                            {view.steps.map((s, i) => <li key={`${s.rule}-${i}`}><StepText text={s.text} /></li>)}
                        </ol>
                    </section>

                    <section aria-labelledby="rujukan-title">
                        <h3 id="rujukan-title" className="font-semibold">Rujukan {view.madhhab_label}</h3>
                        <div className="mt-2"><ReferenceList references={view.references} /></div>
                    </section>

                    <Disclaimer />
                </div>
            )}
        </dialog>
    );
}
