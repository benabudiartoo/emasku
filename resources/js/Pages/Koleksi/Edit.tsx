import { Head, Link } from '@inertiajs/react';
import ItemForm from '@/Components/ItemForm';
import AppLayout from '@/Layouts/AppLayout';
import type { Item } from '@/types';

export default function Edit({ item }: { item: Item }) {
    return (
        <AppLayout>
            <Head title={`Ubah ${item.name}`} />
            <Link href={`/koleksi/${item.id}`} className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Kembali ke detail</Link>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Ubah barang</h1>
            <div className="mt-6">
                <ItemForm kind={item.kind} item={item} cancelHref={`/koleksi/${item.id}`} />
            </div>
        </AppLayout>
    );
}
