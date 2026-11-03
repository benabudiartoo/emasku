import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Coins } from '@phosphor-icons/react';
import Toasts from '@/Components/Toasts';

export default function PublicLayout({ title, subtitle, children }: { title?: string; subtitle?: string; children: ReactNode }) {
    return (
        <>
            <a href="#main" className="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[70] focus:rounded-xl focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:shadow-md">Lewati ke konten</a>
            <main id="main" className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
                <div className="text-center">
                    <Link href="/" className="inline-flex items-center gap-2 font-semibold">
                        <span className="grid h-10 w-10 place-items-center rounded-xl bg-brand text-white"><Coins size={22} aria-hidden /></span>
                        <span className="text-lg" translate="no">Catatan Emas Saya</span>
                    </Link>
                    {title && <h1 className="mt-8 text-2xl font-semibold tracking-tight">{title}</h1>}
                    {subtitle && <p className="mt-1 text-sm text-muted">{subtitle}</p>}
                </div>
                {children}
            </main>
            <Toasts />
        </>
    );
}
