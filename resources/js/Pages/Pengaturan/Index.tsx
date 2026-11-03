import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import type { SharedProps } from '@/types';

export default function Index() {
    const { auth } = usePage<SharedProps>().props;
    const form = useForm({ hide_amounts: auth.user?.hide_amounts ?? false });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch('/pengaturan', { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Pengaturan" />
            <Link href="/menu" className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Kembali</Link>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Pengaturan akun</h1>
            <form onSubmit={submit} className="panel mt-6 space-y-4 p-5">
                <label htmlFor="hide_amounts" className="flex min-h-11 cursor-pointer items-center justify-between gap-4">
                    <span>
                        <span className="block text-sm font-medium">Sembunyikan nominal secara default</span>
                        <span className="block text-sm text-muted">Angka berat dan nilai tampil sebagai titik-titik saat aplikasi dibuka.</span>
                    </span>
                    <input id="hide_amounts" type="checkbox" role="switch" checked={form.data.hide_amounts}
                        onChange={(e) => form.setData('hide_amounts', e.target.checked)} className="h-6 w-11 shrink-0 accent-[#1f6b52]" />
                </label>
                <button type="submit" disabled={form.processing} className="btn btn-primary">Simpan</button>
            </form>
        </AppLayout>
    );
}
