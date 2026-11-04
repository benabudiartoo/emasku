import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';

export default function Impor({ max_mb, max_rows }: { max_mb: number; max_rows: number }) {
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/koleksi/impor', { forceFormData: true });
    };

    return (
        <AppLayout>
            <Head title="Impor dari Excel" />
            <Link href="/koleksi" className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Koleksi</Link>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Impor dari Excel</h1>
            <p className="mt-1 text-sm text-muted">Pindahkan daftar perhiasan dari berkas Excel. Anda akan melihat pratinjau dulu; belum ada yang disimpan sebelum Anda memilih baris dan menekan Simpan.</p>

            <form onSubmit={submit} className="panel mt-6 space-y-4 p-4 sm:p-5" noValidate>
                <div>
                    <label htmlFor="file" className="text-sm font-medium">Berkas Excel (.xlsx)</label>
                    <input id="file" type="file" accept=".xlsx" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                        className="mt-1.5 block w-full min-h-11 rounded-xl border border-line bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-soft file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-dark" />
                    {form.errors.file && <p role="alert" className="mt-1 text-sm text-red-800">{form.errors.file}</p>}
                </div>
                <button type="submit" disabled={form.processing || !form.data.file} className="btn btn-primary">{form.processing ? 'Membaca...' : 'Baca berkas'}</button>
            </form>

            <section aria-labelledby="format-title" className="mt-8">
                <h2 id="format-title" className="text-base font-semibold">Format yang dikenali</h2>
                <ul className="mt-2 space-y-1.5 text-sm leading-relaxed">
                    <li>Sheet dikenali dari judul kolomnya: <strong>Nama Perhiasan</strong> dan <strong>Berat (Gram)</strong> atau <strong>Harga Beli (Rp)</strong>.</li>
                    <li>Kolom lain yang dipakai: <strong>Toko / Lokasi Pembelian</strong>, <strong>Kategori</strong>, <strong>Tanggal Beli</strong>, <strong>Kadar</strong>, dan <strong>POTONGAN</strong> bila ada. Kolom Harga / Gram diabaikan karena dihitung otomatis.</li>
                    <li>Berat dicatat sebagai <strong>berat bersih logam</strong>. Kadar boleh ditulis <code>17K</code>, <code>750</code>, atau <code>75.5%</code>. Bila ada dua angka yang tidak cocok (<code>750/17K</code>), angka karat dipakai dan barisnya ditandai “perlu dicek”.</li>
                    <li>Rumus di Excel tidak dijalankan; hanya teks dan angka di sel yang dibaca.</li>
                    <li>Barang yang sama dengan yang sudah ada ditandai dan tidak dicentang.</li>
                </ul>
                <p className="mt-3 text-sm text-muted">Batas: berkas .xlsx maksimal {max_mb} MB dan {max_rows} baris per sheet. Barang hasil impor bisa diubah lewat form biasa.</p>
                <a href="/koleksi/impor/contoh.xlsx" download className="btn btn-secondary mt-4">Unduh contoh berkas Excel</a>
                <p className="mt-2 text-xs text-muted">Berisi tiga baris contoh dan sheet Petunjuk. Hapus baris contoh, isi data Anda, lalu unggah di sini.</p>
            </section>
        </AppLayout>
    );
}
