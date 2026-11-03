import { Head, Link } from '@inertiajs/react';
import ItemForm from '@/Components/ItemForm';
import AppLayout from '@/Layouts/AppLayout';
import type { Loc } from '@/types';

export default function Baru({ kind, old, locations }: { kind: 'bar' | 'jewelry'; old: boolean; locations: Loc[] }) {
    const title = old ? 'Catat koleksi lama' : kind === 'bar' ? 'Tambah emas batangan' : 'Tambah perhiasan';

    return (
        <AppLayout>
            <Head title={title} />
            <Link href="/koleksi" className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Koleksi Saya</Link>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{title}</h1>
            {old && <p className="mt-1 text-sm text-muted">Untuk emas yang sudah lama Anda miliki. Tanggal mulai dimiliki boleh perkiraan.</p>}
            <div className="mt-6">
                <ItemForm key={old ? 'old' : kind} kind={kind} old={old} locations={locations} cancelHref="/koleksi" />
            </div>
        </AppLayout>
    );
}
