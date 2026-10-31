import { useEffect, useMemo, useRef, useState } from 'react';
import type { ChartMonth } from '@/lib/dashboard';
import { formatAxis, MONTHS, MONTHS_SHORT, niceMax } from '@/lib/dashboard';
import { formatMoney } from '@/lib/format';

type MonthlyChartProps = {
    year: number;
    previousYear: number;
    months: ChartMonth[];
    strongest: { month: number; amount: string } | null;
};

const HEIGHT = 244;
const PAD = { top: 12, right: 8, bottom: 28, left: 44 };

/** Šířka kontejneru, aby se SVG kreslilo v pixelech (písmo se při zmenšení nezmenšuje). */
function useWidth() {
    const ref = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(640);

    useEffect(() => {
        const node = ref.current;
        if (!node) return;
        const update = () => setWidth(Math.max(260, Math.round(node.clientWidth)));
        update();
        const observer = new ResizeObserver(update);
        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    return [ref, width] as const;
}

/**
 * Fakturace po měsících: 12 dvojic sloupců, letos (akcent) a loni (tlumeně). Čisté SVG bez knihovny.
 * Tooltip s oběma částkami se ukáže po najetí, klepnutí nebo focusu sloupce; budoucí měsíce jsou prázdné.
 */
export default function MonthlyChart({ year, previousYear, months, strongest }: MonthlyChartProps) {
    const [ref, width] = useWidth();
    const [active, setActive] = useState<number | null>(null);

    const plotWidth = width - PAD.left - PAD.right;
    const plotHeight = HEIGHT - PAD.top - PAD.bottom;
    const narrow = width < 480;

    const max = useMemo(
        () =>
            niceMax(
                Math.max(...months.flatMap((m) => [Number(m.current ?? 0), Number(m.previous)])),
            ),
        [months],
    );
    const ticks = [0, 0.25, 0.5, 0.75, 1].map((fraction) => max * fraction);
    const y = (value: number) => PAD.top + plotHeight - (value / max) * plotHeight;

    const slot = plotWidth / 12;
    const barWidth = Math.max(4, Math.min(18, slot * 0.34));
    const gap = Math.max(2, slot * 0.06);

    const summary = [
        `Fakturace po měsících, ${year} proti ${previousYear}.`,
        strongest
            ? `Nejsilnější měsíc letos je ${MONTHS[strongest.month - 1]} s ${formatMoney(strongest.amount)}.`
            : 'Letos zatím nic nevyfakturováno.',
    ].join(' ');

    const hovered = active === null ? null : months[active];
    const tooltipLeft = active === null ? 0 : PAD.left + slot * active + slot / 2;

    return (
        <div ref={ref} className="relative">
            <svg
                role="img"
                aria-label={summary}
                width={width}
                height={HEIGHT}
                viewBox={`0 0 ${width} ${HEIGHT}`}
                className="block max-w-full overflow-visible"
                onMouseLeave={() => setActive(null)}
            >
                {ticks.map((tick) => (
                    <g key={tick} aria-hidden="true">
                        <line
                            x1={PAD.left}
                            x2={width - PAD.right}
                            y1={y(tick)}
                            y2={y(tick)}
                            stroke="var(--separator)"
                            strokeWidth="1"
                        />
                        <text
                            x={PAD.left - 8}
                            y={y(tick)}
                            textAnchor="end"
                            dominantBaseline="middle"
                            fontSize="11"
                            fill="var(--label-3)"
                        >
                            {formatAxis(tick)}
                        </text>
                    </g>
                ))}

                {months.map((m, index) => {
                    const center = PAD.left + slot * index + slot / 2;
                    const previous = Number(m.previous);
                    const current = m.current === null ? null : Number(m.current);
                    const selected = active === index;

                    return (
                        <g
                            key={m.month}
                            aria-hidden="true"
                            onMouseEnter={() => setActive(index)}
                            onClick={() => setActive(selected ? null : index)}
                        >
                            <rect
                                x={PAD.left + slot * index}
                                y={PAD.top}
                                width={slot}
                                height={plotHeight + PAD.bottom}
                                fill={selected ? 'var(--fill)' : 'transparent'}
                                rx="6"
                            />
                            {previous > 0 && (
                                <rect
                                    x={center - barWidth - gap / 2}
                                    y={y(previous)}
                                    width={barWidth}
                                    height={Math.max(2, y(0) - y(previous))}
                                    rx="4"
                                    fill="var(--fill-strong)"
                                />
                            )}
                            {current !== null && current > 0 && (
                                <rect
                                    x={center + gap / 2}
                                    y={y(current)}
                                    width={barWidth}
                                    height={Math.max(2, y(0) - y(current))}
                                    rx="3"
                                    fill="var(--accent)"
                                />
                            )}
                            <text
                                x={center}
                                y={HEIGHT - 8}
                                textAnchor="middle"
                                fontSize="11"
                                fill={current === null ? 'var(--label-3)' : 'var(--label-2)'}
                            >
                                {narrow
                                    ? MONTHS_SHORT[index].charAt(0).toUpperCase()
                                    : MONTHS_SHORT[index]}
                            </text>
                        </g>
                    );
                })}
            </svg>

            {hovered && (
                <div
                    role="presentation"
                    className="af-glass pointer-events-none absolute z-10 min-w-[150px] -translate-x-1/2 rounded-[12px] px-3 py-2 text-[12px] leading-4 shadow-[var(--shadow-glass)]"
                    style={{
                        left: Math.min(Math.max(tooltipLeft, 80), width - 80),
                        top: 0,
                    }}
                >
                    <div className="mb-1 font-semibold capitalize">{MONTHS[hovered.month - 1]}</div>
                    <div className="flex items-center justify-between gap-3">
                        <span className="inline-flex items-center gap-1.5">
                            <i className="bg-accent inline-block size-2 rounded-full" />
                            {year}
                        </span>
                        <b className="tabular-nums">
                            {hovered.current === null ? '—' : formatMoney(hovered.current)}
                        </b>
                    </div>
                    <div className="text-label-2 flex items-center justify-between gap-3">
                        <span className="inline-flex items-center gap-1.5">
                            <i className="bg-fill-strong inline-block size-2 rounded-full" />
                            {previousYear}
                        </span>
                        <span className="tabular-nums">{formatMoney(hovered.previous)}</span>
                    </div>
                </div>
            )}

            {/* Totéž jako tabulka pro čtečky; SVG je jen obrázek. */}
            <table className="sr-only">
                <caption>Fakturace po měsících</caption>
                <thead>
                    <tr>
                        <th scope="col">Měsíc</th>
                        <th scope="col">{year}</th>
                        <th scope="col">{previousYear}</th>
                    </tr>
                </thead>
                <tbody>
                    {months.map((m) => (
                        <tr key={m.month}>
                            <th scope="row">{MONTHS[m.month - 1]}</th>
                            <td>{m.current === null ? 'zatím nic' : formatMoney(m.current)}</td>
                            <td>{formatMoney(m.previous)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
