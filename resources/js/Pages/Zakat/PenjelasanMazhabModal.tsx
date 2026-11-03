import Modal from '@/Components/Modal';
import { gram } from '@/lib/format';
import type { ZakatMadhhab } from '@/types';
import { Disclaimer, ReferenceList } from './parts';

/** Madhhab differences and references, read from the rule sheets the server offers; nothing is written here. */
export default function PenjelasanMazhabModal({ open, onClose, madhhabs }: { open: boolean; onClose: () => void; madhhabs: ZakatMadhhab[] }) {
    return (
        <Modal open={open} onClose={onClose} title="Tentang mazhab dan rujukan">
            <div className="max-h-[60vh] space-y-5 overflow-y-auto pr-1">
                <p className="text-sm leading-relaxed text-muted">
                    Mazhab berbeda pada hal yang mengubah angka. Hanya mazhab yang aturannya lengkap dan berrujukan yang ditawarkan.
                </p>
                {madhhabs.map((m) => (
                    <section key={m.key} aria-label={m.label}>
                        <h3 className="font-semibold">{m.label} <span className="text-xs font-normal text-muted">aturan v{m.version}</span></h3>
                        <ul className="mt-1 list-disc space-y-0.5 pl-5 text-sm">
                            <li>Nisab: {gram(m.nisab_g, 0)} g emas murni, tarif 2,5%.</li>
                            <li>Perhiasan yang dipakai: {m.worn_jewelry_zakatable ? 'ikut dihitung.' : 'tidak dihitung; perhiasan simpanan tetap dihitung.'}</li>
                        </ul>
                        <div className="mt-2"><ReferenceList references={m.references} /></div>
                    </section>
                ))}
                <Disclaimer />
            </div>
            <button type="button" onClick={onClose} className="btn btn-secondary mt-4 w-full">Tutup</button>
        </Modal>
    );
}
