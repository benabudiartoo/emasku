import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowRight, FileXls, HourglassMedium, PlusCircle } from '@phosphor-icons/react';
import PilihJenisModal from '@/Components/PilihJenisModal';
import AppLayout from '@/Layouts/AppLayout';

const CARD = 'panel flex flex-col items-start gap-3 p-5 text-left transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2';

export default function Mulai() {
    const [pick, setPick] = useState(false);

    return (
        <AppLayout>
            <Head title="Mulai" />
            <header>
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Selamat datang</h1>
                <p className="mt-1 max-w-[52ch] text-sm leading-relaxed text-muted">Mau mulai dari mana? Pilih salah satu. Anda bisa menambah yang lain kapan saja.</p>
            </header>
            <section aria-label="Cara memulai" className="mt-6 grid gap-4 sm:grid-cols-2">
                <button type="button" onClick={() => setPick(true)} className={CARD}>
                    <span className="grid h-11 w-11 place-items-center rounded-xl bg-brand-soft text-brand-dark"><PlusCircle size={26} aria-hidden /></span>
                    <span className="block text-lg font-semibold">Tambah emas pertama</span>
                    <span className="block text-sm leading-relaxed text-muted">Catat batangan atau perhiasan yang ada di tangan Anda sekarang.</span>
                    <span className="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-brand-dark">Pilih jenis<ArrowRight size={16} aria-hidden /></span>
                </button>
                <Link href="/koleksi/lama" className={CARD}>
                    <span className="grid h-11 w-11 place-items-center rounded-xl bg-brand-soft text-brand-dark"><HourglassMedium size={26} aria-hidden /></span>
                    <span className="block text-lg font-semibold">Catat koleksi lama</span>
                    <span className="block text-sm leading-relaxed text-muted">Untuk emas warisan atau yang dibeli sudah lama, dengan tanggal dan tingkat kepastian.</span>
                    <span className="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-brand-dark">Lanjut<ArrowRight size={16} aria-hidden /></span>
                </Link>
                <Link href="/koleksi/impor" className={CARD}>
                    <span className="grid h-11 w-11 place-items-center rounded-xl bg-brand-soft text-brand-dark"><FileXls size={26} aria-hidden /></span>
                    <span className="block text-lg font-semibold">Impor dari Excel</span>
                    <span className="block text-sm leading-relaxed text-muted">Sudah punya daftar perhiasan di Excel? Pindahkan sekaligus, dengan pratinjau dulu.</span>
                    <span className="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-brand-dark">Pilih berkas<ArrowRight size={16} aria-hidden /></span>
                </Link>
            </section>
            <p className="mt-6 text-center">
                <Link href="/ringkasan" className="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-medium text-muted underline underline-offset-2 hover:text-ink">Lewati dulu</Link>
            </p>
            <PilihJenisModal open={pick} onClose={() => setPick(false)} />
        </AppLayout>
    );
}
