import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Field from '@/Components/Field';
import AppLayout from '@/Layouts/AppLayout';
import { gram } from '@/lib/format';
import type { PriceFeed, PriceQuote, SharedProps, ZakatMadhhab, ZakatView } from '@/types';
import CaraHitungDrawer from './CaraHitungDrawer';
import { ContributionList, Disclaimer, ResultPanel } from './parts';

type Inputs = { date: string; madhhab: string; price: string; haul_start: string; jewelry_confirmed: boolean };

type Props = {
    madhhabs: ZakatMadhhab[];
    inputs: Inputs;
    has_jewelry: boolean;
    result: ZakatView | null;
    form_errors: Record<string, string>;
    price_feed: PriceFeed;
};

/** Only filled fields are sent, so the URL stays short and an empty price is simply absent. */
const toParams = (d: Inputs) => ({
    date: d.date,
    madhhab: d.madhhab,
    ...(d.price.trim() ? { price: d.price.trim() } : {}),
    ...(d.haul_start ? { haul_start: d.haul_start } : {}),
    ...(d.jewelry_confirmed ? { jewelry_confirmed: '1' } : {}),
});

export default function Hitung({ madhhabs, inputs, has_jewelry, result, form_errors, price_feed }: Props) {
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [data, setData] = useState<Inputs>(inputs);
    const [drawer, setDrawer] = useState(false);
    const [busy, setBusy] = useState(false);
    const [feed, setFeed] = useState<PriceQuote | null>(null);
    const [feedError, setFeedError] = useState<string | null>(null);
    const [feedBusy, setFeedBusy] = useState(false);

    // Fills the price field from the server lookup. The field stays editable and manual entry always works.
    const fetchPrice = async () => {
        setFeedBusy(true);
        setFeedError(null);
        try {
            const res = await fetch('/zakat/harga-emas', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (res.status === 429) throw new Error('Terlalu sering mengambil harga. Tunggu sebentar atau isi manual.');
            const body = await res.json().catch(() => null);
            if (!res.ok || !body?.price_per_gram) throw new Error(body?.message ?? 'Harga tidak bisa diambil sekarang. Isi manual.');
            setFeed(body as PriceQuote);
            setData((d) => ({ ...d, price: (body as PriceQuote).price_per_gram }));
        } catch (e) {
            setFeed(null);
            setFeedError(e instanceof Error && e.message ? e.message : 'Harga tidak bisa diambil sekarang. Isi manual.');
        } finally {
            setFeedBusy(false);
        }
    };

    const fetchedAt = feed ? new Date(feed.fetched_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';

    const allErrors = { ...form_errors, ...errors };
    // The result on screen belongs to the inputs last sent; saving is only offered while the form still matches.
    const changed = JSON.stringify(toParams(data)) !== JSON.stringify(toParams(inputs));

    const preview = (e: FormEvent) => {
        e.preventDefault();
        router.get('/zakat/hitung', toParams(data), { preserveScroll: true, preserveState: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };

    // The server recomputes from these inputs; no result is sent.
    const save = () => router.post('/zakat/periode', toParams(inputs), { onStart: () => setBusy(true), onFinish: () => setBusy(false) });

    const set = <K extends keyof Inputs>(key: K, value: Inputs[K]) => setData((d) => ({ ...d, [key]: value }));

    return (
        <AppLayout>
            <Head title="Hitung zakat" />
            <Link href="/zakat" className="inline-flex min-h-11 items-center text-sm font-medium text-brand-dark underline underline-offset-2">Zakat</Link>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Hitung zakat</h1>
            <p className="mt-1 text-sm text-muted">Dihitung dari emas milik sendiri yang tercatat. Titipan orang lain tidak ikut.</p>

            <form onSubmit={preview} className="mt-6 space-y-6" noValidate>
                <section className="panel space-y-4 p-4 sm:p-5">
                    <Field id="date" label="Tanggal penilaian" error={allErrors.date}>
                        <input id="date" type="date" required value={data.date} max={new Date().toISOString().slice(0, 10)} onChange={(e) => set('date', e.target.value)} className="field-input" />
                    </Field>

                    <fieldset>
                        <legend className="text-sm font-medium">Mazhab</legend>
                        <div className="mt-1.5 grid gap-2">
                            {madhhabs.map((m) => (
                                <label key={m.key} className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 ${data.madhhab === m.key ? 'border-brand bg-brand-soft' : 'border-line bg-white'}`}>
                                    <input type="radio" name="madhhab" value={m.key} checked={data.madhhab === m.key} onChange={() => set('madhhab', m.key)} className="mt-1 accent-[#1f6b52]" />
                                    <span>
                                        <span className="block font-medium">{m.label}</span>
                                        <span className="block text-sm text-muted">
                                            Nisab {gram(m.nisab_g, 0)} g emas murni · perhiasan dipakai {m.worn_jewelry_zakatable ? 'dihitung' : 'tidak dihitung'}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {allErrors.madhhab && <p role="alert" className="mt-1 text-sm text-red-800">{allErrors.madhhab}</p>}
                    </fieldset>

                    <Field id="price" label="Harga emas per gram (Rp, opsional)" error={allErrors.price} hint="Diisi bila Anda ingin melihat zakat dalam rupiah. Disimpan bersama perhitungan.">
                        <div className="flex gap-2">
                            <input id="price" inputMode="decimal" value={data.price} placeholder="1500000" onChange={(e) => set('price', e.target.value)} className="field-input min-w-0 flex-1" />
                            {price_feed.available && (
                                <button type="button" onClick={fetchPrice} disabled={feedBusy} className="btn btn-secondary mt-1.5 shrink-0">
                                    {feedBusy ? 'Mengambil...' : 'Ambil harga terbaru'}
                                </button>
                            )}
                        </div>
                        {feedError && <p role="alert" className="mt-1 text-sm text-red-800">{feedError}</p>}
                        {feed && (
                            <p role="status" className="mt-1 text-xs leading-relaxed text-muted">
                                Diambil pukul {fetchedAt}. Harga spot internasional ({feed.source}) dikonversi ke rupiah dengan kurs {feed.fx_source}{feed.fx_date ? ` (${feed.fx_date})` : ''}; bukan harga toko emas. Anda bisa mengubahnya.
                            </p>
                        )}
                        {price_feed.available && (
                            <p className="mt-1 text-xs text-muted">Sumber harga otomatis: <a href={price_feed.attribution.url} target="_blank" rel="noopener noreferrer" className="font-medium text-brand-dark underline underline-offset-2">{price_feed.attribution.name}</a></p>
                        )}
                    </Field>

                    <Field id="haul_start" label="Awal haul (opsional)" error={allErrors.haul_start} hint="Bila diisi, menggantikan awal haul yang dihitung dari tanggal tiap barang.">
                        <input id="haul_start" type="date" value={data.haul_start} onChange={(e) => set('haul_start', e.target.value)} className="field-input" />
                    </Field>

                    {has_jewelry && (
                        <div>
                            <label className="flex min-h-11 cursor-pointer items-start gap-3 text-sm">
                                <input type="checkbox" checked={data.jewelry_confirmed} onChange={(e) => set('jewelry_confirmed', e.target.checked)} className="mt-0.5 h-5 w-5 accent-[#1f6b52]" />
                                <span>Saya sudah memeriksa tujuan tiap perhiasan (dipakai atau simpanan). Perhiasan diperlakukan berbeda antar mazhab.</span>
                            </label>
                            {allErrors.jewelry_confirmed && <p role="alert" className="mt-1 text-sm text-red-800">{allErrors.jewelry_confirmed}</p>}
                        </div>
                    )}
                </section>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link href="/zakat" className="btn btn-secondary">Batal</Link>
                    <button type="submit" disabled={busy} className="btn btn-primary">Lihat hasil</button>
                </div>
            </form>

            {result && (
                <section aria-label="Hasil perhitungan" className="mt-8 space-y-5">
                    <ResultPanel view={result} editLinks />

                    <div>
                        <h2 className="text-base font-semibold">Barang yang dihitung</h2>
                        <div className="mt-3"><ContributionList view={result} /></div>
                    </div>

                    {changed && <p role="status" className="rounded-xl border border-warn-line bg-warn-soft p-3 text-sm">Isian berubah sejak hasil ini dihitung. Tekan “Lihat hasil” lagi sebelum menyimpan.</p>}

                    <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <button type="button" onClick={() => setDrawer(true)} className="btn btn-secondary">Lihat cara hitung</button>
                        <button type="button" onClick={save} disabled={busy || changed} className="btn btn-primary">Simpan salinan periode</button>
                    </div>
                    <Disclaimer />
                    <CaraHitungDrawer open={drawer} onClose={() => setDrawer(false)} view={result} />
                </section>
            )}
        </AppLayout>
    );
}
