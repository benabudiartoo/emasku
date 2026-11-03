import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { PencilSimple, Plus, Trash } from '@phosphor-icons/react';
import LocationModal, { LOCATION_KINDS } from '@/Components/LocationModal';
import Modal from '@/Components/Modal';
import AppLayout from '@/Layouts/AppLayout';
import type { Loc } from '@/types';

export default function Lokasi({ locations }: { locations: Loc[] }) {
    const [editing, setEditing] = useState<Loc | null>(null);
    const [adding, setAdding] = useState(false);
    const [deleting, setDeleting] = useState<Loc | null>(null);

    return (
        <AppLayout>
            <Head title="Lokasi simpan" />
            <Link href="/menu" className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Kembali</Link>
            <div className="flex items-end justify-between gap-4">
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Lokasi simpan</h1>
                <button type="button" onClick={() => setAdding(true)} className="btn btn-primary"><Plus size={18} aria-hidden />Tambah lokasi</button>
            </div>

            {locations.length === 0 ? (
                <p className="panel mt-6 p-6 text-center text-sm text-muted">Belum ada lokasi. Tambahkan tempat Anda menyimpan emas, mis. “Brankas rumah”.</p>
            ) : (
                <ul className="panel mt-6 divide-y divide-line">
                    {locations.map((l) => (
                        <li key={l.id} className="flex items-center gap-3 px-4 py-3">
                            <span className="min-w-0 flex-1">
                                <span className="block truncate font-medium">{l.name}</span>
                                <span className="block text-sm text-muted">{LOCATION_KINDS[l.kind] ?? l.kind} · {l.items_count ?? 0} barang</span>
                            </span>
                            <button type="button" aria-label={`Ubah ${l.name}`} onClick={() => setEditing(l)} className="btn btn-secondary !px-3"><PencilSimple size={18} aria-hidden /></button>
                            <button type="button" aria-label={`Hapus ${l.name}`} onClick={() => setDeleting(l)} className="btn btn-secondary !px-3"><Trash size={18} aria-hidden /></button>
                        </li>
                    ))}
                </ul>
            )}

            <LocationModal open={adding} onClose={() => setAdding(false)} />
            <LocationModal open={!!editing} onClose={() => setEditing(null)} location={editing} />
            <Modal open={!!deleting} onClose={() => setDeleting(null)} title="Hapus lokasi?">
                <p className="text-sm text-muted">Lokasi yang masih berisi barang tidak bisa dihapus.</p>
                <div className="mt-4 flex gap-3">
                    <button type="button" onClick={() => setDeleting(null)} className="btn btn-secondary flex-1">Batal</button>
                    <button type="button" onClick={() => deleting && router.delete(`/pengaturan/lokasi/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })} className="btn btn-primary flex-1">Hapus</button>
                </div>
            </Modal>
        </AppLayout>
    );
}
