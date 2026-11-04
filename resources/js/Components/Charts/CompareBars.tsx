import type { ReactNode } from 'react';
import { SERIES_GOLD, SERIES_GREEN, TableView } from './ChartCard';

type Bar = { label: string; value: string; color: string };

/**
 * Two bars on one rupiah scale: what was paid and what the same items are worth now. Two series, so a legend names
 * them; the value sits on each bar's line. Both bars start at zero so the lengths compare honestly.
 */
export default function CompareBars({ cost, value, format, valueLabel = 'Nilai sekarang', children }: { cost: string; value: string; format: (v: string) => string; valueLabel?: string; children?: ReactNode }) {
    const bars: Bar[] = [
        { label: 'Harga perolehan', value: cost, color: SERIES_GOLD },
        { label: valueLabel, value, color: SERIES_GREEN },
    ];
    const max = Math.max(...bars.map((b) => Number(b.value)), 0);

    return (
        <div>
            <ul className="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-sm" aria-label="Keterangan warna">
                {bars.map((b) => (
                    <li key={b.label} className="inline-flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: b.color }} aria-hidden />{b.label}
                    </li>
                ))}
            </ul>
            <ul className="space-y-3">
                {bars.map((b) => (
                    <li key={b.label}>
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span>{b.label}</span>
                            <span className="font-semibold tabular-nums">{format(b.value)}</span>
                        </div>
                        <div className="mt-1 h-3 w-full" aria-hidden>
                            <div className="h-3 rounded-r-[4px]" style={{ width: `${Math.max(max > 0 ? (Number(b.value) / max) * 100 : 0, 1)}%`, backgroundColor: b.color }} />
                        </div>
                    </li>
                ))}
            </ul>
            {children}
            <TableView caption={`${valueLabel} dan harga perolehan`} head={['Ukuran', 'Rupiah']} rows={bars.map((b) => [b.label, format(b.value)])} />
        </div>
    );
}
