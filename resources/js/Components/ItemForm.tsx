import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import Field from '@/Components/Field';
import Modal from '@/Components/Modal';
import { DATE_CERTAINTY, inputNumber } from '@/lib/format';
import type { Item } from '@/types';

type Props = {
    kind: 'bar' | 'jewelry';
    old?: boolean;
    item?: Item;
    cancelHref: string;
};

const JEWELRY_TYPES: Record<string, string> = { cincin: 'Cincin', kalung: 'Kalung', gelang: 'Gelang', anting: 'Anting', liontin: 'Liontin', lainnya: 'Lainnya' };
const PURPOSES: Record<string, string> = { worn: 'Dipakai', investment: 'Simpanan / investasi', undecided: 'Belum ditentukan' };
const BRANDS = ['Antam', 'Galeri 24', 'Lotus Archi', 'UBS', 'Hartadinata', 'Lainnya'];

export default function ItemForm({ kind: initialKind, old = false, item, cancelHref }: Props) {
    const editing = !!item;
    const [discardOpen, setDiscardOpen] = useState(false);

    const form = useForm({
        kind: initialKind as string,
        old,
        then: 'done',
        name: item?.name ?? '',
        // A brand typed freely before the list existed is shown as "Lainnya" rather than dropped.
        brand: item?.brand ? (BRANDS.includes(item.brand) ? item.brand : 'Lainnya') : '',
        ownership: item?.ownership ?? 'own',
        held_for: item?.held_for ?? '',
        unit_weight_g: inputNumber(item?.unit_weight_g ?? null),
        quantity: String(item?.kind === 'bar' ? item.pieces : 1),
        jewelry_type: item?.jewelry_type ?? '',
        gross_weight_g: inputNumber(item?.gross_weight_g ?? null),
        stone_state: item?.stone_state ?? 'unknown',
        stone_weight_g: inputNumber(item?.stone_weight_g ?? null),
        net_weight_g: inputNumber(item?.net_weight_g ?? null),
        purpose: item?.purpose ?? 'undecided',
        // Purity is always entered in karat; an item saved in another unit is shown converted.
        purity_input: inputNumber(item ? (item.purity_source === 'karat' ? item.purity_input : item.purity_karat) : null),
        acquisition_cost: inputNumber(item?.acquisition_cost ?? null),
        purchase_store: item?.purchase_store ?? '',
        resale_discount_percent: inputNumber(item?.resale_discount_percent ?? null),
        owned_since: item?.owned_since ?? '',
        owned_since_certainty: item?.owned_since_certainty ?? 'exact',
        haul_start: item?.haul_start ?? '',
        haul_start_certainty: item?.haul_start_certainty ?? 'exact',
        haul_start_note: item?.haul_start_note ?? '',
        notes: item?.notes ?? '',
        photo: null as File | null,
    });
    const { data, setData, errors } = form;
    const isBar = data.kind === 'bar';

    const send = (then: 'done' | 'another') => {
        form.transform((d) => ({ ...d, then, ...(editing ? { _method: 'patch' } : {}) }));
        form.post(editing ? `/koleksi/${item!.id}` : '/koleksi', { forceFormData: true, preserveScroll: true });
    };
    const submit = (e: FormEvent) => { e.preventDefault(); send('done'); };

    const cancel = () => (form.isDirty ? setDiscardOpen(true) : router.visit(cancelHref));
    const text = (id: keyof typeof data, props: Record<string, unknown> = {}) => (
        <input id={id} value={String(data[id] ?? '')} onChange={(e) => setData(id, e.target.value as never)} className="field-input" {...props} />
    );
    const select = (id: keyof typeof data, options: Record<string, string>, placeholder?: string) => (
        <select id={id} value={String(data[id] ?? '')} onChange={(e) => setData(id, e.target.value as never)} className="field-input">
            {placeholder !== undefined && <option value="">{placeholder}</option>}
            {Object.entries(options).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
    );
    const err = (k: string) => (errors as Record<string, string>)[k];

    return (
        <>
            <form onSubmit={submit} className="space-y-6" noValidate>
                {old && !editing && (
                    <div role="group" aria-label="Jenis barang" className="inline-flex rounded-xl border border-line bg-white p-1">
                        {(['bar', 'jewelry'] as const).map((k) => (
                            <button key={k} type="button" aria-pressed={data.kind === k} onClick={() => setData('kind', k)}
                                className={`h-9 rounded-lg px-4 text-sm font-medium ${data.kind === k ? 'bg-brand text-white' : 'text-muted'}`}>
                                {k === 'bar' ? 'Batangan' : 'Perhiasan'}
                            </button>
                        ))}
                    </div>
                )}

                <Section title="Identitas">
                    <Field id="name" label="Nama barang" error={err('name')}>
                        {text('name', { required: true, maxLength: 160, placeholder: isBar ? 'Batangan Antam 10 g' : 'Cincin kawin' })}
                    </Field>
                    {isBar
                        ? <Field id="brand" label="Merek (opsional)" error={err('brand')}>{select('brand', Object.fromEntries(BRANDS.map((b) => [b, b])), 'Pilih merek')}</Field>
                        : <Field id="jewelry_type" label="Jenis perhiasan" error={err('jewelry_type')}>{select('jewelry_type', JEWELRY_TYPES, 'Pilih jenis')}</Field>}
                    <Field id="ownership" label="Kepemilikan" error={err('ownership')} hint="Titipan orang lain tidak dihitung dalam total Anda.">
                        {select('ownership', { own: 'Milik sendiri', held_for_others: 'Titipan orang lain' })}
                    </Field>
                    {data.ownership === 'held_for_others' && (
                        <Field id="held_for" label="Titipan siapa" error={err('held_for')}>{text('held_for', { placeholder: 'Nama pemilik' })}</Field>
                    )}
                </Section>

                <Section title="Berat dan kadar">
                    {isBar ? (
                        <>
                            <Field id="unit_weight_g" label="Berat per keping (gram)" error={err('unit_weight_g')} hint="Kosongkan bila belum tahu; angka akan ditandai Data diperlukan.">
                                {text('unit_weight_g', { inputMode: 'decimal', placeholder: '10' })}
                            </Field>
                            <Field id="quantity" label="Jumlah keping" error={err('quantity')}>{text('quantity', { inputMode: 'numeric', placeholder: '1' })}</Field>
                        </>
                    ) : (
                        <>
                            <Field id="gross_weight_g" label="Berat kotor (gram)" error={err('gross_weight_g')}>{text('gross_weight_g', { inputMode: 'decimal', placeholder: '8' })}</Field>
                            <Field id="stone_state" label="Batu" error={err('stone_state')} hint="“Belum diketahui” tidak sama dengan tanpa batu.">
                                {select('stone_state', { none: 'Tanpa batu', known: 'Ada batu, berat diketahui', unknown: 'Belum diketahui' })}
                            </Field>
                            {data.stone_state === 'known' && (
                                <Field id="stone_weight_g" label="Berat batu (gram)" error={err('stone_weight_g')}>{text('stone_weight_g', { inputMode: 'decimal', placeholder: '2' })}</Field>
                            )}
                            <Field id="net_weight_g" label="Berat bersih logam (gram, opsional)" error={err('net_weight_g')} hint="Bila ditimbang sendiri, angka ini dipakai menggantikan kotor dikurangi batu.">
                                {text('net_weight_g', { inputMode: 'decimal' })}
                            </Field>
                            <Field id="purpose" label="Tujuan" error={err('purpose')}>{select('purpose', PURPOSES)}</Field>
                        </>
                    )}
                    <Field id="purity_input" label="Kadar (karat)" error={err('purity_input')} hint="Mis. 24 untuk emas batangan, 17 atau 18 untuk perhiasan. Kadar karat dihitung sebagai perkiraan.">
                        <div className="relative">
                            {text('purity_input', { inputMode: 'decimal', placeholder: isBar ? '24' : '18', className: 'field-input pr-16' })}
                            <span aria-hidden className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted">karat</span>
                        </div>
                    </Field>
                </Section>

                <Section title="Pembelian dan tanggal">
                    <Field id="acquisition_cost" label="Harga beli (Rp, opsional)" error={err('acquisition_cost')}>{text('acquisition_cost', { inputMode: 'numeric', placeholder: 'Kosong = tidak diketahui' })}</Field>
                    <Field id="purchase_store" label="Toko / tempat pembelian (opsional)" error={err('purchase_store')}>{text('purchase_store', { maxLength: 120, placeholder: 'Mis. Toko Emas Mulia Gold Palembang' })}</Field>
                    {!isBar && (
                        <Field id="resale_discount_percent" label="Potongan jual (%, opsional)" error={err('resale_discount_percent')} hint="Perkiraan potongan toko bila perhiasan ini dijual. Dipakai untuk memperkirakan harga jual.">
                            {text('resale_discount_percent', { inputMode: 'decimal', placeholder: 'Mis. 10 atau 15' })}
                        </Field>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field id="owned_since" label="Dimiliki sejak" error={err('owned_since')} hint="Berbeda dari tanggal Anda mencatat di aplikasi ini.">
                            {text('owned_since', { type: 'date', max: new Date().toISOString().slice(0, 10) })}
                        </Field>
                        <Field id="owned_since_certainty" label="Kepastian tanggal" error={err('owned_since_certainty')}>{select('owned_since_certainty', DATE_CERTAINTY)}</Field>
                    </div>
                    {(old || data.haul_start) && (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field id="haul_start" label="Awal haul diketahui (opsional)" error={err('haul_start')}>
                                    {text('haul_start', { type: 'date', max: new Date().toISOString().slice(0, 10) })}
                                </Field>
                                <Field id="haul_start_certainty" label="Kepastian awal haul" error={err('haul_start_certainty')}>{select('haul_start_certainty', DATE_CERTAINTY)}</Field>
                            </div>
                            <Field id="haul_start_note" label="Catatan awal haul (opsional)" error={err('haul_start_note')}>{text('haul_start_note')}</Field>
                        </>
                    )}
                </Section>

                <Section title="Foto dan catatan">
                    <Field id="photo" label={editing ? 'Tambah foto (opsional)' : 'Foto (opsional)'} error={err('photo')} hint="JPG, PNG, atau WebP, maksimal 5 MB. Foto bersifat pribadi.">
                        <input id="photo" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setData('photo', e.target.files?.[0] ?? null)}
                            className="mt-1.5 block w-full text-sm file:mr-3 file:h-11 file:rounded-xl file:border file:border-line file:bg-white file:px-4 file:text-sm file:font-medium" />
                    </Field>
                    <Field id="notes" label="Catatan (opsional)" error={err('notes')}>
                        <textarea id="notes" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="field-input !h-auto py-2" />
                    </Field>
                </Section>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" onClick={cancel} className="btn btn-secondary">Batal</button>
                    {old && !editing && (
                        <button type="button" disabled={form.processing} onClick={() => send('another')} className="btn btn-secondary">Simpan dan tambah lagi</button>
                    )}
                    <button type="submit" disabled={form.processing} className="btn btn-primary">{old && !editing ? 'Simpan dan selesai' : 'Simpan'}</button>
                </div>
            </form>

            <Modal open={discardOpen} onClose={() => setDiscardOpen(false)} title="Buang perubahan?">
                <p className="text-sm text-muted">Isian yang belum disimpan akan hilang.</p>
                <div className="mt-4 flex gap-3">
                    <button type="button" onClick={() => setDiscardOpen(false)} className="btn btn-secondary flex-1">Lanjut mengisi</button>
                    <button type="button" onClick={() => router.visit(cancelHref)} className="btn btn-primary flex-1">Buang</button>
                </div>
            </Modal>
        </>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <fieldset className="panel space-y-4 p-4 sm:p-5">
            <legend className="px-1 text-base font-semibold">{title}</legend>
            {children}
        </fieldset>
    );
}
