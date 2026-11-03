import { SERIES_GREEN, TableView } from './ChartCard';

type Row = { label: string; value: string };

/**
 * Horizontal bars for one measure: the label and its value on a line, a thin bar below. One series, so no legend; the
 * title names it. Bars start at zero, are 12 px thick with a rounded data end, and the value sits at the bar's end of
 * the line. Lengths are drawing geometry only; the shown numbers are the server's strings.
 */
export default function BarList({ rows, format, head }: { rows: Row[]; format: (value: string) => string; head: [string, string] }) {
    const max = Math.max(...rows.map((r) => Number(r.value)), 0);

    return (
        <div>
            <ul className="space-y-3">
                {rows.map((r) => {
                    const pct = max > 0 ? (Number(r.value) / max) * 100 : 0;
                    return (
                        <li key={r.label}>
                            <div className="flex items-baseline justify-between gap-3 text-sm">
                                <span className="min-w-0 truncate" translate="no">{r.label}</span>
                                <span className="shrink-0 font-semibold tabular-nums">{format(r.value)}</span>
                            </div>
                            <div className="mt-1 h-3 w-full" aria-hidden>
                                <div className="h-3 rounded-r-[4px]" style={{ width: `${Math.max(pct, 1)}%`, backgroundColor: SERIES_GREEN }} />
                            </div>
                        </li>
                    );
                })}
            </ul>
            <TableView caption={head[0]} head={head} rows={rows.map((r) => [r.label, format(r.value)])} />
        </div>
    );
}
