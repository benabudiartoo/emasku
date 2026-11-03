import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { dateId } from '@/lib/format';
import type { AdminTotals, AdminUsers } from '@/types';

const TOTALS: { key: keyof AdminTotals; label: string }[] = [
    { key: 'users', label: 'Pengguna' },
    { key: 'active_30d', label: 'Aktif 30 hari' },
    { key: 'new_30d', label: 'Baru 30 hari' },
    { key: 'items', label: 'Barang tercatat' },
    { key: 'zakat_periods', label: 'Perhitungan zakat' },
];

/** '2026-11-03 14:05' -> '3 Nov 2026, 14:05'. */
function loginText(value: string | null): string {
    if (!value) return 'Belum pernah masuk';
    const [date, time] = value.split(' ');
    return `${dateId(date)}, ${time}`;
}

export default function Index({ totals, users }: { totals: AdminTotals; users: AdminUsers }) {
    return (
        <AppLayout>
            <Head title="Admin" />
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Admin</h1>
            <p className="mt-1 text-sm text-muted">Hanya jumlah. Isi emas, berat, nilai, dan hasil zakat setiap pengguna tidak ditampilkan.</p>

            <section aria-label="Ringkasan sistem" className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                {TOTALS.map((t) => (
                    <div key={t.key} className="panel p-4">
                        <p className="text-sm text-muted">{t.label}</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">{totals[t.key]}</p>
                    </div>
                ))}
            </section>

            <section aria-labelledby="pengguna-title" className="mt-8">
                <h2 id="pengguna-title" className="text-base font-semibold">Pengguna</h2>
                <p className="mt-0.5 text-sm text-muted">{users.total === 0 ? 'Belum ada pengguna.' : `Menampilkan ${users.from}-${users.to} dari ${users.total}, terbaru dulu.`}</p>

                <ul className="panel mt-3 divide-y divide-line overflow-hidden">
                    {users.data.map((u) => (
                        <li key={u.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium">
                                    <span className="truncate" translate="no">{u.name}</span>
                                    {u.is_admin && <span className="shrink-0 rounded-md bg-brand-soft px-1.5 py-0.5 text-xs font-semibold text-brand-dark">Admin</span>}
                                </p>
                                <p className="truncate text-sm text-muted" translate="no">{u.email}</p>
                            </div>
                            <div className="text-sm text-muted sm:text-right">
                                <p>Daftar {u.created_at ? dateId(u.created_at) : '-'}</p>
                                <p>Masuk: {loginText(u.last_login_at)}</p>
                            </div>
                            <p className="text-sm font-medium sm:w-36 sm:text-right">{u.items_count} barang · {u.zakat_periods_count} zakat</p>
                        </li>
                    ))}
                </ul>

                {users.last_page > 1 && (
                    <nav aria-label="Halaman pengguna" className="mt-4 flex items-center justify-between gap-3">
                        {users.prev_url ? <Link href={users.prev_url} preserveScroll className="btn btn-secondary">Sebelumnya</Link> : <span />}
                        <span className="text-sm text-muted">Halaman {users.current_page} dari {users.last_page}</span>
                        {users.next_url ? <Link href={users.next_url} preserveScroll className="btn btn-secondary">Berikutnya</Link> : <span />}
                    </nav>
                )}
            </section>
        </AppLayout>
    );
}
