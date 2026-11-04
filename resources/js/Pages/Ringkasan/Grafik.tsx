import { useEffect, useState } from 'react';
import BarList from '@/Components/Charts/BarList';
import ChartCard from '@/Components/Charts/ChartCard';
import CompareBars from '@/Components/Charts/CompareBars';
import GrowthChart from '@/Components/Charts/GrowthChart';
import { dateId, gram, rupiahCents } from '@/lib/format';
import type { PortfolioValueData, SummaryChartsData } from '@/types';

const g = (v: string) => `${gram(v)} g`;

const idr = rupiahCents;

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
    const r = value?.resale ?? null;
    const at = value ? new Date(value.price.fetched_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';

    return (
        <section aria-labelledby="grafik-title" className="mt-10">
            <h2 id="grafik-title" className="text-lg font-semibold">Grafik</h2>
            <p className="mt-1 text-sm text-muted">Hanya barang milik sendiri; barang titipan tidak ikut. Grafik emas murni memerlukan data lengkap (termasuk kadar), sedangkan grafik pertumbuhan memakai berat bersih sehingga tidak butuh kadar.</p>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <ChartCard title="Emas murni per jenis" subtitle="Dalam gram">
                    <BarList rows={charts.by_kind.map((r) => ({ label: r.label, value: r.pure_gold_g }))} format={g} head={['Jenis', 'Emas murni']} />
                </ChartCard>

                <ChartCard title="Emas murni per lokasi" subtitle="Dalam gram; lima terbesar, sisanya digabung">
                    <BarList rows={charts.by_location.map((r) => ({ label: r.label, value: r.pure_gold_g }))} format={g} head={['Lokasi', 'Emas murni']} />
                </ChartCard>

                <ChartCard title="Pertumbuhan total berat emas" subtitle="Total gram (berat bersih, bukan hanya emas murni) menurut tanggal dimiliki sejak">
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
                                        <p className="mt-1 text-xs text-muted">Dihitung dari {c.items_with_cost} barang yang harga belinya diisi, dibanding nilai barang yang sama.</p>
                                        {c.items_without_cost > 0 && <p className="mt-1 text-xs text-muted">{c.items_without_cost} barang tanpa harga beli tidak ikut dibandingkan.</p>}
                                    </CompareBars>
                                </div>
                            ) : (
                                <p className="mt-3 text-sm text-muted">Belum ada barang dengan harga beli, jadi belum ada pembanding. Isi “Harga beli” pada barang untuk melihat selisihnya.</p>
                            )}

                            <p className="mt-4 text-xs leading-relaxed text-muted">
                                Harga {value.price.source} dengan kurs {value.price.fx_source}{value.price.fx_date ? ` (${dateId(value.price.fx_date)})` : ''}, diambil pukul {at}. {value.price.note}
                            </p>
                        </>
                    )}
                </ChartCard>

                <ChartCard title="Perkiraan hasil jual" subtitle="Barang dengan potongan jual, dalam rupiah">
                    {loading && <p role="status" className="text-sm text-muted">Mengambil harga terbaru...</p>}
                    {error && <p role="alert" className="text-sm text-red-800">{error} Perkiraan hasil jual memakai harga pasar, jadi belum bisa ditampilkan.</p>}
                    {value && r && (r.items_estimated === 0 ? (
                        <p className="text-sm text-muted">
                            Belum ada barang yang bisa diperkirakan. Isi “Potongan jual” dan “Harga beli” pada barang Anda.
                            {r.items_not_estimable > 0 && ` ${r.items_not_estimable} barang sudah punya potongan tetapi datanya belum lengkap.`}
                        </p>
                    ) : (
                        <>
                            <p className="text-sm text-muted">Perkiraan hasil jual</p>
                            <p className="text-2xl font-semibold tracking-tight">{idr(r.estimated_total)}</p>
                            <div className="mt-4">
                                <CompareBars cost={r.cost} value={r.estimated_total} format={idr} valueLabel="Perkiraan hasil jual">
                                    <p className="mt-3 text-sm font-medium">
                                        Selisih {signed(r.difference, idr)}
                                        {r.difference_percent !== null && ` (${signed(r.difference_percent, (a) => `${gram(a, 1)}%`)})`}
                                    </p>
                                    <p className="mt-1 text-xs text-muted">Dari {r.items_estimated} barang, dibanding harga beli barang yang sama.</p>
                                    {r.items_using_market > 0 && <p className="mt-1 text-xs text-muted">{r.items_using_market} barang memakai harga pasar karena lebih tinggi dari harga setelah potongan.</p>}
                                    {r.items_not_estimable > 0 && <p className="mt-1 text-xs text-muted">{r.items_not_estimable} barang punya potongan tetapi belum lengkap datanya, jadi tidak ikut.</p>}
                                </CompareBars>
                            </div>
                            <p className="mt-4 text-xs leading-relaxed text-muted">Ini perkiraan, bukan janji harga toko. Harga pasar: {value.price.source} dengan kurs {value.price.fx_source}, diambil pukul {at}.</p>
                        </>
                    ))}
                </ChartCard>
            </div>
        </section>
    );
}
