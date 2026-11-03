import { useState } from 'react';
import { GRID, SERIES_GREEN, SURFACE, TableView } from './ChartCard';

type Point = { date: string; cumulative_g: string };

const W = 360;
const H = 210;
const LEFT = 40;
const RIGHT = 14;
const TOP = 26;
const BOTTOM = 26;

/** A round number at or above `value` (1, 1.2, 1.5, 2, 2.5, 3, 4, 5, 6, 8 times a power of ten), so the axis ends on a clean tick. */
function niceMax(value: number): number {
    if (value <= 0) return 1;
    const pow = 10 ** Math.floor(Math.log10(value));
    const step = [1, 1.2, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10].find((s) => s * pow >= value) ?? 10;
    return step * pow;
}

/**
 * Cumulative pure gold over time as a step line: holdings only change on the dates items were acquired, so the line
 * holds flat between them. One series, so no legend. 2 px line, a light wash under it, an 8 px end dot with a surface
 * ring, one value label (the hovered point, or the latest), and recessive hairline grid. Coordinates are drawing
 * geometry only; every shown number is the server's string.
 */
export default function GrowthChart({ points, formatGram, formatDate }: { points: Point[]; formatGram: (v: string) => string; formatDate: (d: string) => string }) {
    const [active, setActive] = useState<number | null>(null);

    const values = points.map((p) => Number(p.cumulative_g));
    const top = niceMax(Math.max(...values));
    const times = points.map((p) => Date.parse(p.date));
    const t0 = times[0];
    const t1 = times[times.length - 1];
    const plotW = W - LEFT - RIGHT;
    const plotH = H - TOP - BOTTOM;

    const x = (i: number) => (t1 === t0 ? LEFT + plotW / 2 : LEFT + ((times[i] - t0) / (t1 - t0)) * plotW);
    const y = (v: number) => TOP + plotH - (v / top) * plotH;
    const base = y(0);

    let line = `M ${x(0)} ${base} V ${y(values[0])}`;
    for (let i = 1; i < points.length; i++) line += ` H ${x(i)} V ${y(values[i])}`;
    const area = `${line} V ${base} Z`;

    const shown = active ?? points.length - 1;
    const label = `${formatGram(points[shown].cumulative_g)} · ${formatDate(points[shown].date)}`;
    const anchor = x(shown) > W * 0.6 ? 'end' : 'start';
    const first = points[0];
    const last = points[points.length - 1];
    const summary = points.length === 1
        ? `Emas murni ${formatGram(last.cumulative_g)} sejak ${formatDate(last.date)}.`
        : `Emas murni naik dari ${formatGram(first.cumulative_g)} pada ${formatDate(first.date)} menjadi ${formatGram(last.cumulative_g)} pada ${formatDate(last.date)}.`;

    const ticks = [0, top / 2, top];

    return (
        <div>
            <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={summary} className="h-auto w-full" onMouseLeave={() => setActive(null)}>
                {ticks.map((t) => (
                    <g key={t}>
                        <line x1={LEFT} x2={W - RIGHT} y1={y(t)} y2={y(t)} stroke={GRID} strokeWidth={1} />
                        <text x={LEFT - 6} y={y(t) + 4} textAnchor="end" fontSize={10} fill="#55635c">{Number.isInteger(t) ? t : t.toFixed(1).replace('.', ',')}</text>
                    </g>
                ))}
                <text x={LEFT - 6} y={12} textAnchor="end" fontSize={10} fill="#55635c">gram</text>

                <path d={area} fill={SERIES_GREEN} fillOpacity={0.1} />
                <path d={line} fill="none" stroke={SERIES_GREEN} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />

                <text x={LEFT} y={H - 8} textAnchor="start" fontSize={10} fill="#55635c">{formatDate(first.date)}</text>
                {points.length > 1 && <text x={W - RIGHT} y={H - 8} textAnchor="end" fontSize={10} fill="#55635c">{formatDate(last.date)}</text>}

                <circle cx={x(shown)} cy={y(values[shown])} r={4} fill={SERIES_GREEN} stroke={SURFACE} strokeWidth={2} />
                <text x={x(shown) + (anchor === 'end' ? -8 : 8)} y={y(values[shown]) - 10} textAnchor={anchor} fontSize={11} fontWeight={600} fill="#14201b">{label}</text>

                {points.map((p, i) => (
                    <circle key={p.date} cx={x(i)} cy={y(values[i])} r={12} fill="transparent" tabIndex={0}
                        aria-label={`${formatDate(p.date)}: ${formatGram(p.cumulative_g)}`}
                        onMouseEnter={() => setActive(i)} onFocus={() => setActive(i)} onBlur={() => setActive(null)} />
                ))}
            </svg>
            <TableView caption="Pertumbuhan emas murni" head={['Tanggal', 'Total emas murni']} rows={points.map((p) => [formatDate(p.date), formatGram(p.cumulative_g)])} />
        </div>
    );
}
