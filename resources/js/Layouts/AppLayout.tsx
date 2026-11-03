import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Coins, Eye, EyeSlash, List, SquaresFour, Vault } from '@phosphor-icons/react';
import { initHidden, toggleHidden, useHidden } from '@/Components/Secret';
import Toasts from '@/Components/Toasts';
import type { SharedProps } from '@/types';

const TABS = [
    { href: '/ringkasan', label: 'Ringkasan', Icon: SquaresFour },
    { href: '/koleksi', label: 'Koleksi', Icon: Vault },
];

export default function AppLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage<SharedProps>();
    initHidden(props.auth.user?.hide_amounts ?? false);
    const hidden = useHidden();
    const path = url.split('?')[0];
    const active = (href: string) => path === href || path.startsWith(`${href}/`);

    return (
        <div className="min-h-screen bg-paper text-ink">
            <a href="#main" className="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[70] focus:rounded-xl focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:shadow-md">Lewati ke konten</a>
            <header className="fixed inset-x-0 top-0 z-20 h-14 border-b border-line bg-paper/95 backdrop-blur">
                <div className="mx-auto flex h-full max-w-5xl items-center justify-between gap-3 px-4 sm:px-6">
                    <Link href="/ringkasan" className="flex shrink-0 items-center gap-2 font-semibold">
                        <span className="grid h-8 w-8 place-items-center rounded-xl bg-brand text-white"><Coins size={18} aria-hidden /></span>
                        <span translate="no">Catatan Emas Saya</span>
                    </Link>
                    <nav aria-label="Navigasi utama" className="hidden items-center gap-1 text-sm md:flex">
                        {TABS.map(({ href, label }) => (
                            <Link key={href} href={href} aria-current={active(href) ? 'page' : undefined}
                                className={`inline-flex h-9 items-center rounded-xl px-3 font-medium transition-colors hover:bg-black/5 ${active(href) ? 'bg-brand-soft text-brand-dark' : 'text-muted'}`}>
                                {label === 'Koleksi' ? 'Koleksi Saya' : label}
                            </Link>
                        ))}
                    </nav>
                    <div className="flex items-center gap-1">
                        <button type="button" onClick={toggleHidden} aria-pressed={hidden} aria-label={hidden ? 'Tampilkan nominal' : 'Sembunyikan nominal'}
                            className={`inline-flex h-10 w-10 items-center justify-center rounded-xl hover:bg-black/5 ${hidden ? 'bg-brand-soft text-brand-dark' : 'text-muted'}`}>
                            {hidden ? <EyeSlash size={22} aria-hidden /> : <Eye size={22} aria-hidden />}
                        </button>
                        <Link href="/menu" aria-label="Menu lainnya" className="inline-flex h-10 w-10 items-center justify-center rounded-xl text-muted hover:bg-black/5">
                            <List size={22} aria-hidden />
                        </Link>
                    </div>
                </div>
            </header>

            <nav aria-label="Navigasi utama" className="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-white/95 backdrop-blur md:hidden">
                <ul className="mx-auto grid max-w-md grid-cols-2">
                    {TABS.map(({ href, label, Icon }) => (
                        <li key={href}>
                            <Link href={href} aria-current={active(href) ? 'page' : undefined}
                                className={`flex w-full flex-col items-center gap-0.5 pb-2 pt-2 text-[0.6875rem] font-medium ${active(href) ? 'font-semibold text-brand-dark' : 'text-muted'}`}>
                                <Icon size={24} aria-hidden />
                                {label}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>

            <main id="main" className="mx-auto max-w-5xl px-4 pb-28 pt-20 sm:px-6 md:pb-12">{children}</main>
            <Toasts />
        </div>
    );
}
