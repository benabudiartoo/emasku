import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Secret from '@/Components/Secret';
import { dateId, gram, rupiahCents } from '@/lib/format';
import type { Item, ResaleEstimateData } from '@/types';

const MISSING_TEXT: Record<string, string> = {
    acquisition_cost: 'harga perolehan',
    resale_discount_percent: 'potongan jual',
    net_weight: 'berat bersih',
};

const REASON_TEXT: Record<string, string> = {
    price_unavailable: 'harga pasar tidak tersedia saat ini',
    purity_unknown: 'kadar barang belum diisi, jadi nilai pasarnya belum bisa dihitung',
};

const BASIS_TEXT: Record<string, string> = {
    market: 'Harga pasar barang ini lebih tinggi dari harga setelah potongan, jadi harga pasar yang dipakai.',
    discounted: 'Harga setelah potongan sama atau lebih tinggi dari harga pasar barang ini, jadi harga setelah potongan yang dipakai.',
};

/** The resale estimate of one item: what the discount gives, what the market gives, and which of the two is used. */
export default function ResaleBlock({ item }: { item: Item }) {
    const [data, setData] = useState<ResaleEstimateData | null>(null);
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState(false);
    const hasDiscount = item.resale_discount_percent !== null;

    useEffect(() => {
        if (!hasDiscount) {
            setLoading(false);
            return;
        }
        let alive = true;
        fetch(`/koleksi/${item.id}/perkiraan-jual`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(async (res) => {
                const body = await res.json().catch(() => null);
                if (!res.ok || !body?.estimate) throw new Error('failed');
                if (alive) setData(body as ResaleEstimateData);
            })
            .catch(() => alive && setFailed(true))
            .finally(() => alive && setLoading(false));
        return () => { alive = false; };
    }, [item.id, hasDiscount]);

    if (item.ownership !== 'own') return null;

    const edit = <Link href={`/koleksi/${item.id}/edit`} className="inline-flex min-h-11 items-center text-sm font-semibold text-brand-dark underline underline-offset-2">Ubah barang</Link>;

    if (!hasDiscount) {
        return (
            <section aria-label="Perkiraan harga jual" className="panel mt-5 p-4">
                <h2 className="text-base font-semibold">Perkiraan harga jual</h2>
                <p className="mt-1 text-sm text-muted">Isi “Potongan jual” pada barang ini untuk melihat perkiraan harga jualnya.</p>
                {edit}
            </section>
        );
    }

    const e = data?.estimate ?? null;
    const missing = e && !e.estimable ? e.missing : [];
    const at = data?.price ? new Date(data.price.fetched_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';

    return (
        <section aria-label="Perkiraan harga jual" className="panel mt-5 p-4">
            <h2 className="text-base font-semibold">Perkiraan harga jual</h2>

            {loading && <p role="status" className="mt-1 text-sm text-muted">Menghitung...</p>}
            {failed && <p role="alert" className="mt-1 text-sm text-red-800">Perkiraan tidak bisa dimuat sekarang. Coba muat ulang halaman.</p>}

            {missing.length > 0 && (
                <div className="mt-1">
                    <p className="text-sm text-muted">Belum bisa diperkirakan. Lengkapi: {missing.map((m) => MISSING_TEXT[m]).join(', ')}.</p>
                    {edit}
                </div>
            )}

            {e?.estimable && e.estimate !== null && e.discounted !== null && (
                <>
                    <dl className="mt-2 divide-y divide-line text-sm">
                        <div className="flex justify-between gap-3 py-2"><dt className="text-muted">Harga beli per gram</dt><dd className="font-medium"><Secret>{item.price_per_gram === null ? '-' : rupiahCents(item.price_per_gram)}</Secret></dd></div>
                        <div className="flex justify-between gap-3 py-2"><dt className="text-muted">Potongan jual</dt><dd className="font-medium">{gram(item.resale_discount_percent, 0)}%</dd></div>
                        <div className="flex justify-between gap-3 py-2"><dt className="text-muted">Harga setelah potongan</dt><dd className="font-medium"><Secret>{rupiahCents(e.discounted)}</Secret></dd></div>
                        <div className="flex justify-between gap-3 py-2">
                            <dt className="text-muted">Harga pasar barang ini</dt>
                            <dd className="font-medium">{e.market === null ? <span className="text-muted">Tidak tersedia</span> : <Secret>{rupiahCents(e.market)}</Secret>}</dd>
                        </div>
                    </dl>

                    <p className="mt-3 text-sm text-muted">Perkiraan harga jual</p>
                    <p className="text-2xl font-semibold tracking-tight"><Secret>{rupiahCents(e.estimate)}</Secret></p>
                    <p className="mt-1 text-sm leading-relaxed">
                        {e.basis === 'discounted_only'
                            ? `Tanpa pembanding harga pasar: ${REASON_TEXT[e.market_reason ?? 'price_unavailable']}. Yang tampil hanya harga setelah potongan.`
                            : BASIS_TEXT[e.basis ?? 'discounted']}
                    </p>
                    <p className="mt-2 text-xs leading-relaxed text-muted">
                        Ini perkiraan, bukan janji harga toko.
                        {data?.price ? ` Harga pasar: ${data.price.source} dengan kurs ${data.price.fx_source}${data.price.fx_date ? ` (${dateId(data.price.fx_date)})` : ''}, diambil pukul ${at}. ${data.price.note}` : ` ${data?.price_message ?? ''}`}
                    </p>
                </>
            )}
        </section>
    );
}
