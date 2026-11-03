import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import type { Loc } from '@/types';

export const LOCATION_KINDS: Record<string, string> = { home: 'Rumah', safe: 'Brankas', bank_box: 'Safe deposit bank', other: 'Lainnya' };

/** Create (no `location`) or rename (with `location`) a named storage place. */
export default function LocationModal({ open, onClose, location }: { open: boolean; onClose: () => void; location?: Loc | null }) {
    const form = useForm({ name: '', kind: 'home' });

    useEffect(() => {
        if (open) {
            form.setData({ name: location?.name ?? '', kind: location?.kind ?? 'home' });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, location?.id]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };
        if (location) form.patch(`/pengaturan/lokasi/${location.id}`, options);
        else form.post('/pengaturan/lokasi', options);
    };

    return (
        <Modal open={open} onClose={onClose} title="Lokasi simpan">
            <form onSubmit={submit} className="space-y-4">
                <Field id="loc-name" label="Nama lokasi" error={form.errors.name}>
                    <input id="loc-name" required maxLength={120} value={form.data.name} placeholder="Brankas rumah"
                        onChange={(e) => form.setData('name', e.target.value)} className="field-input" />
                </Field>
                <Field id="loc-kind" label="Jenis" error={form.errors.kind}>
                    <select id="loc-kind" value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)} className="field-input">
                        {Object.entries(LOCATION_KINDS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                </Field>
                <div className="flex gap-3">
                    <button type="button" onClick={onClose} className="btn btn-secondary flex-1">Batal</button>
                    <button type="submit" disabled={form.processing} className="btn btn-primary flex-1">Simpan</button>
                </div>
            </form>
        </Modal>
    );
}
