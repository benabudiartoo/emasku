import { Head, Link, router, usePage } from '@inertiajs/react';
import { Gear, MapPin, ShieldCheck, SignOut } from '@phosphor-icons/react';
import AppLayout from '@/Layouts/AppLayout';
import type { SharedProps } from '@/types';

const ROW = 'flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand';

export default function Menu() {
    const isAdmin = usePage<SharedProps>().props.auth.user?.is_admin === true;

    return (
        <AppLayout>
            <Head title="Menu" />
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Menu lainnya</h1>
            <ul className="panel mt-6 divide-y divide-line overflow-hidden">
                <li><Link href="/pengaturan" className={ROW}><Gear size={22} className="text-brand" aria-hidden />Pengaturan akun</Link></li>
                <li><Link href="/pengaturan/lokasi" className={ROW}><MapPin size={22} className="text-brand" aria-hidden />Lokasi simpan</Link></li>
                {isAdmin && <li><Link href="/admin" className={ROW}><ShieldCheck size={22} className="text-brand" aria-hidden />Admin</Link></li>}
                <li><button type="button" onClick={() => router.post('/logout')} className={ROW}><SignOut size={22} className="text-brand" aria-hidden />Keluar</button></li>
            </ul>
        </AppLayout>
    );
}
