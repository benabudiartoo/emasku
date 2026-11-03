import type { ReactNode } from 'react';
import { EyeSlash } from '@phosphor-icons/react';
import { useHidden } from '@/Components/Secret';

/** Series colors, checked with the dataviz validator (light surface): value/grams in green, purchase price in gold. */
export const SERIES_GREEN = '#12805a';
export const SERIES_GOLD = '#c98a00';
export const SURFACE = '#ffffff';
export const GRID = '#dde2dc';

/**
 * One chart in a panel. While "Sembunyikan nominal" is on, the content is replaced by a message: bar lengths and
 * line shapes would give away the very numbers the toggle hides.
 */
export default function ChartCard({ title, subtitle, children }: { title: string; subtitle?: string; children: ReactNode }) {
    const hidden = useHidden();

    return (
        <section aria-label={title} className="panel p-4 sm:p-5">
            <h3 className="text-base font-semibold">{title}</h3>
            {subtitle && <p className="mt-0.5 text-sm text-muted">{subtitle}</p>}
            <div className="mt-4">
                {hidden ? (
                    <p className="flex min-h-24 items-center justify-center gap-2 rounded-xl bg-paper px-4 text-sm text-muted">
                        <EyeSlash size={18} aria-hidden />Nominal disembunyikan
                    </p>
                ) : children}
            </div>
        </section>
    );
}

/** The same figures as a table, for people who prefer numbers and for assistive technology. */
export function TableView({ caption, head, rows }: { caption: string; head: string[]; rows: string[][] }) {
    return (
        <details className="mt-3 text-sm">
            <summary className="inline-flex min-h-11 cursor-pointer items-center font-medium text-brand-dark underline underline-offset-2">Lihat sebagai tabel</summary>
            <table className="mt-1 w-full text-left">
                <caption className="sr-only">{caption}</caption>
                <thead>
                    <tr className="border-b border-line text-muted">{head.map((h, i) => <th key={h} scope="col" className={`py-1.5 font-medium ${i > 0 ? 'text-right' : ''}`}>{h}</th>)}</tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r[0]} className="border-b border-line last:border-0">
                            {r.map((c, i) => <td key={i} className={`py-1.5 ${i > 0 ? 'text-right tabular-nums' : ''}`}>{c}</td>)}
                        </tr>
                    ))}
                </tbody>
            </table>
        </details>
    );
}
