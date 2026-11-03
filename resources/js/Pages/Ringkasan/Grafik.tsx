import { useEffect, useState } from 'react';
import BarList from '@/Components/Charts/BarList';
import ChartCard from '@/Components/Charts/ChartCard';
import CompareBars from '@/Components/Charts/CompareBars';
import GrowthChart from '@/Components/Charts/GrowthChart';
import { dateId, gram } from '@/lib/format';
import type { PortfolioValueData, SummaryChartsData } from '@/types';

const g = (v: string) => `${gram(v)} g`;

/** Rupiah as the server computed it: whole rupiah when the cents are 00, otherwise both decimals ("909,20", not "909,2"). */
const idr = (v: string) => `Rp ${v.endsWith('.00') ? gram(v.slice(0, -3), 0) : gram(v, 2)}`;

/** '-131438749.81' -> '-Rp 131.438.749,81' style pieces: the sign is shown, the amount is formatted without it. */
const signed = (v: string, text: (abs: string) => string) => (v.startsWith('-') ? `-${text(v.slice(1))}` : `+${text(v)}`);

/** The charts on the summary page. Gram charts come with the page; the value chart is fetched after it opens. */
export default function Grafik({ charts }: { charts: SummaryChartsData }) {
    const [value, setValue] = useState<PortfolioValueData | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let alive = true;
        fetch('/ringkasan/nilai', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(async (res) => {
                if (res.status === 429) throw new Error('Terlalu sering memuat harga. Coba lagi sebentar lagi.');
                const body = await res.json().catch(() => null);
                if (!res.ok || !body?.total_value) throw new Error(body?.message ?? 'Harga tidak bisa diambil sekarang.');
                if (alive) setValue(body as PortfolioValueData);
            })
            .catch((e: unknown) => alive && setError(e instanceof Error && e.message ? e.message : 'Harga tidak bisa diambil sekarang.'))
            .finally(() => alive && setLoading(false));
        return () => { alive = false; };
    }, []);

    if (charts.by_kind.length === 0) {
        return (
            <section aria-labelledby="grafik-title" className="mt-10">
                <h2 id="grafik-title" className="text-lg font-semibold">Grafik</h2>
                <p className="panel mt-3 p-5 text-sm text-muted">Belum ada barang yang datanya lengkap, jadi grafik belum bisa dibuat. Lengkapi data barang Anda lebih dulu.</p>
            </section>
        );
    }

    const { growth } = charts;
    const c = value?.comparison ?? null;
    const at = value ? new Date(value.price.fetched_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';

    return (
        <section aria-labelledby="grafik-title" className="mt-10">
            <h2 id="grafik-title" className="text-lg font-semibold">Grafik</h2>
            <p className="mt-1 text-sm text-muted">Hanya barang milik sendiri yang datanya lengkap. Barang titipan dan barang yang belum lengkap tidak ikut.</p>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <ChartCard title="Emas murni per jenis" subtitle="Dalam gram">
                    <BarList rows={charts.by_kind.map((r) => ({ label: r.label, value: r.pure_gold_g }))} format={g} head={['Jenis', 'Emas murni']} />
                </ChartCard>

                <ChartCard title="Emas murni per lokasi" subtitle="Dalam gram; lima terbesar, sisanya digabung">
                    <BarList rows={charts.by_location.map((r) => ({ label: r.label, value: r.pure_gold_g }))} format={g} head={['Lokasi', 'Emas murni']} />
                </ChartCard>

                <ChartCard title="Pertumbuhan emas murni" subtitle="Total gram menurut tanggal dimiliki sejak">
                    {growth.points.length === 0 ? (
                        <p className="text-sm text-muted">Belum ada barang dengan tanggal dimiliki sejak, jadi pertumbuhan belum bisa digambar.</p>
                    ) : (
                        <GrowthChart points={growth.points} formatGram={g} formatDate={dateId} />
                    )}
                    {growth.undated_count > 0 && <p className="mt-2 text-xs text-muted">{growth.undated_count} barang tidak digambar karena tanggal dimilikinya belum diisi.</p>}
                    {growth.approximate_count > 0 && <p className="mt-1 text-xs text-muted">{growth.approximate_count} barang bertanggal perkiraan.</p>}
                </ChartCard>

                <ChartCard title="Nilai sekarang vs harga beli" subtitle="Dalam rupiah">
                    {loading && <p role="status" className="text-sm text-muted">Mengambil harga terbaru...</p>}
                    {error && <p role="alert" className="text-sm text-red-800">{error} Grafik gram tetap bisa dipakai; nilai rupiah tidak ditampilkan.</p>}
                    {value && (
                        <>
                            <p className="text-sm text-muted">Nilai seluruh koleksi sekarang</p>
                            <p className="text-2xl font-semibold tracking-tight">{idr(value.total_value)}</p>

                            {c ? (
                                <div className="mt-4">
                                    <CompareBars cost={c.cost} value={c.value} format={idr}>
                                        <p className="mt-3 text-sm font-medium">
                                            Selisih {signed(c.difference, idr)}
                                            {c.difference_percent !== null && ` (${signed(c.difference_percent, (a) => `${gram(a, 1)}%`)})`}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">Dihitung dari {c.items_with_cost} barang yang harga perolehannya diisi, dibanding nilai barang yang sama.</p>
                                        {c.items_without_cost > 0 && <p className="mt-1 text-xs text-muted">{c.items_without_cost} barang tanpa harga perolehan tidak ikut dibandingkan.</p>}
                                    </CompareBars>
                                </div>
                            ) : (
                                <p className="mt-3 text-sm text-muted">Belum ada barang dengan harga perolehan, jadi belum ada pembanding. Isi “Harga perolehan” pada barang untuk melihat selisihnya.</p>
                            )}

                            <p className="mt-4 text-xs leading-relaxed text-muted">
                                Harga {value.price.source} dengan kurs {value.price.fx_source}{value.price.fx_date ? ` (${dateId(value.price.fx_date)})` : ''}, diambil pukul {at}. {value.price.note}
                            </p>
                        </>
                    )}
                </ChartCard>
            </div>
        </section>
    );
}
