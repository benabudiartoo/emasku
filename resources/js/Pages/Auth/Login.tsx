import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';
import PasswordInput from '@/Components/PasswordInput';

export default function Login() {
    const form = useForm({ email: '', password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <PublicLayout title="Masuk" subtitle="Lanjutkan mencatat emas Anda.">
            <Head title="Masuk" />
            <form onSubmit={submit} className="panel mt-6 space-y-4 p-5 sm:p-6">
                <div>
                    <label htmlFor="email" className="block text-sm font-medium">Email</label>
                    <input
                        id="email" name="email" type="email" autoComplete="username" inputMode="email" required
                        placeholder="nama@contoh.com" value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)} className="field-input"
                    />
                </div>
                <div>
                    <label htmlFor="password" className="block text-sm font-medium">Kata sandi</label>
                    <PasswordInput id="password" value={form.data.password} onChange={(v) => form.setData('password', v)} autoComplete="current-password" />
                </div>
                <button type="submit" disabled={form.processing} className="btn btn-primary w-full">Masuk</button>
            </form>
            <p className="mt-6 text-center text-sm text-muted">
                Belum punya akun?{' '}
                <Link href="/register" className="inline-flex min-h-11 items-center rounded-md font-semibold text-brand-dark underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Daftar</Link>
            </p>
        </PublicLayout>
    );
}
