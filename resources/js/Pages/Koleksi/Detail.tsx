import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { WarningCircle } from '@phosphor-icons/react';
import Modal from '@/Components/Modal';
import Secret from '@/Components/Secret';
import AppLayout from '@/Layouts/AppLayout';
import { ACQUISITION_LABEL, DATE_CERTAINTY, KIND_LABEL, dateId, gram, missingText, purityLabel, rupiah } from '@/lib/format';
import type { Item } from '@/types';

type Photo = { id: string; name: string; url: string };

const NEEDS = (
    <span className="inline-flex items-center gap-1 rounded-lg border border-warn-line bg-warn-soft px-2 py-0.5 text-sm font-medium text-warn">
        <WarningCircle size={14} aria-hidden />Data diperlukan
    </span>
);

export default function Detail({ item, photos }: { item: Item; photos: Photo[] }) {
    const [photo, setPhoto] = useState<Photo | null>(null);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const complete = item.status === 'complete';
    const weight = (v: string | null) => (v === null ? NEEDS : <><Secret>{gram(v)}</Secret> g</>);

    const rows: [string, ReactNode][] = [
        ['Jenis', item.kind === 'jewelry' && item.jewelry_type ? `${KIND_LABEL.jewelry} · ${item.jewelry_type}` : KIND_LABEL[item.kind]],
        ['Kadar', item.purity_source ? <>{purityLabel(item.purity_source, item.purity_input)}{item.purity_estimate && <span className="text-muted"> (perkiraan dari karat)</span>}</> : NEEDS],
        ...(item.kind === 'bar'
            ? ([
                ['Berat per keping', weight(item.unit_weight_g)],
                ['Jumlah keping', item.pieces > 0 ? String(item.pieces) : NEEDS],
            ] as [string, ReactNode][])
            : ([
                ['Berat kotor', weight(item.gross_weight_g)],
                ['Batu', item.stone_state === 'none' ? 'Tanpa batu' : item.stone_state === 'known' ? weight(item.stone_weight_g) : 'Belum diketahui'],
            ] as [string, ReactNode][])),
        ['Berat bersih logam', weight(item.net_g)],
        ['Kepemilikan', item.ownership === 'own' ? 'Milik sendiri' : `Titipan${item.held_for ? ` · ${item.held_for}` : ''} (tidak dihitung dalam total)`],
        ['Lokasi', item.location?.name ?? 'Belum ditentukan'],
        ['Cara perolehan', ACQUISITION_LABEL[item.acquisition_method] ?? item.acquisition_method],
        ['Harga perolehan', item.acquisition_cost === null ? 'Tidak diketahui' : <Secret>{rupiah(item.acquisition_cost)}</Secret>],
        ['Dimiliki sejak', item.owned_since ? `${dateId(item.owned_since)} (${DATE_CERTAINTY[item.owned_since_certainty]?.toLowerCase()})` : 'Tidak diketahui'],
        ...(item.owned_since_note ? ([['Catatan tanggal', item.owned_since_note]] as [string, ReactNode][]) : []),
        ...(item.haul_start ? ([['Awal haul', `${dateId(item.haul_start)} (${DATE_CERTAINTY[item.haul_start_certainty ?? 'exact']?.toLowerCase()})`]] as [string, ReactNode][]) : []),
        ['Dicatat pada', dateId(item.created_at)],
        ...(item.notes ? ([['Catatan', item.notes]] as [string, ReactNode][]) : []),
    ];

    return (
        <AppLayout>
            <Head title={item.name} />
            <div className="flex items-center justify-between gap-3">
                <Link href="/koleksi" className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Kembali</Link>
                <Link href={`/koleksi/${item.id}/edit`} className="btn btn-secondary">Ubah</Link>
            </div>

            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{item.name}</h1>
            {item.brand && <p className="mt-1 text-sm text-muted">{item.brand}</p>}

            <section aria-label="Emas murni" className="panel mt-5 p-5">
                <p className="text-sm text-muted">Emas murni</p>
                {complete ? (
                    <p className="mt-1 text-3xl font-semibold"><Secret>{gram(item.pure_gold_g)}</Secret> g</p>
                ) : (
                    <div className="mt-2">
                        {NEEDS}
                        <p className="mt-2 text-sm text-muted">Lengkapi {missingText(item.missing)} agar emas murni bisa dihitung. Barang ini belum masuk total.</p>
                        <Link href={`/koleksi/${item.id}/edit`} className="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-brand-dark underline underline-offset-2">Lengkapi</Link>
                    </div>
                )}
            </section>

            {photos.length > 0 && (
                <section aria-label="Foto" className="mt-5 flex flex-wrap gap-3">
                    {photos.map((p) => (
                        <button key={p.id} type="button" onClick={() => setPhoto(p)} className="overflow-hidden rounded-xl border border-line focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                            <img src={p.url} alt={p.name} className="h-24 w-24 object-cover" />
                        </button>
                    ))}
                </section>
            )}

            <section aria-label="Data barang" className="panel mt-5 divide-y divide-line">
                <dl>
                    {rows.map(([label, value]) => (
                        <div key={label} className="flex flex-col gap-0.5 px-4 py-3 sm:flex-row sm:gap-4">
                            <dt className="text-sm text-muted sm:w-48 sm:shrink-0">{label}</dt>
                            <dd className="min-w-0 break-words text-sm font-medium">{value}</dd>
                        </div>
                    ))}
                </dl>
            </section>

            {item.kind === 'bar' && item.units.some((u) => u.serial_number) && (
                <section aria-label="Nomor seri" className="panel mt-5 p-4">
                    <h2 className="text-base font-semibold">Nomor seri</h2>
                    <ul className="mt-2 text-sm">{item.units.map((u) => <li key={u.id}>{u.serial_number ?? 'Tanpa nomor seri'}</li>)}</ul>
                </section>
            )}

            <button type="button" onClick={() => setConfirmDelete(true)} className="mt-8 inline-flex min-h-11 items-center text-sm font-medium text-red-800 underline underline-offset-2">Hapus barang</button>

            <Modal open={!!photo} onClose={() => setPhoto(null)} title="Foto">
                {photo && <img src={photo.url} alt={photo.name} className="max-h-[70vh] w-full rounded-lg object-contain" />}
            </Modal>
            <Modal open={confirmDelete} onClose={() => setConfirmDelete(false)} title="Hapus barang?">
                <p className="text-sm text-muted">Barang dan fotonya dihapus permanen.</p>
                <div className="mt-4 flex gap-3">
                    <button type="button" onClick={() => setConfirmDelete(false)} className="btn btn-secondary flex-1">Batal</button>
                    <button type="button" onClick={() => router.delete(`/koleksi/${item.id}`)} className="btn btn-primary flex-1">Hapus</button>
                </div>
            </Modal>
        </AppLayout>
    );
}
