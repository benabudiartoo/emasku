import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';
import PasswordInput from '@/Components/PasswordInput';

export default function Register() {
    const form = useForm({ name: '', email: '', password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/register', { onFinish: () => form.reset('password') });
    };

    const err = (k: 'name' | 'email' | 'password') =>
        form.errors[k] ? <p id={`${k}-error`} role="alert" className="mt-1 text-sm text-red-800">{form.errors[k]}</p> : null;

    return (
        <PublicLayout title="Buat akun" subtitle="Catatan Anda hanya bisa dilihat oleh Anda.">
            <Head title="Daftar" />
            <form onSubmit={submit} className="panel mt-6 space-y-4 p-5 sm:p-6">
                <div>
                    <label htmlFor="name" className="block text-sm font-medium">Nama</label>
                    <input id="name" name="name" type="text" autoComplete="name" required value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)} aria-describedby={form.errors.name ? 'name-error' : undefined} className="field-input" />
                    {err('name')}
                </div>
                <div>
                    <label htmlFor="email" className="block text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" autoComplete="username" inputMode="email" required
                        placeholder="nama@contoh.com" value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)} aria-describedby={form.errors.email ? 'email-error' : undefined} className="field-input" />
                    {err('email')}
                </div>
                <div>
                    <label htmlFor="password" className="block text-sm font-medium">Kata sandi</label>
                    <PasswordInput id="password" value={form.data.password} onChange={(v) => form.setData('password', v)} autoComplete="new-password" describedBy="password-hint" />
                    <p id="password-hint" className="mt-1 text-xs text-muted">Minimal 8 karakter.</p>
                    {err('password')}
                </div>
                <button type="submit" disabled={form.processing} className="btn btn-primary w-full">Daftar</button>
            </form>
            <p className="mt-6 text-center text-sm text-muted">
                Sudah punya akun?{' '}
                <Link href="/login" className="inline-flex min-h-11 items-center rounded-md font-semibold text-brand-dark underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Masuk</Link>
            </p>
        </PublicLayout>
    );
}
