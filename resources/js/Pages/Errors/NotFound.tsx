import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function NotFound({ signed_in }: { signed_in: boolean }) {
    return (
        <PublicLayout title="Halaman tidak ditemukan" subtitle="Alamatnya salah, atau barang itu bukan milik akun ini.">
            <Head title="Tidak ditemukan" />
            <div className="mt-6 text-center">
                <Link href={signed_in ? '/ringkasan' : '/login'} className="btn btn-primary">
                    {signed_in ? 'Kembali ke Ringkasan' : 'Kembali ke Masuk'}
                </Link>
            </div>
        </PublicLayout>
    );
}
