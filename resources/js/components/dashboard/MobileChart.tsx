import { useState } from 'react';
import type { ChartMonth } from '@/lib/dashboard';
import { MONTHS, MONTHS_SHORT } from '@/lib/dashboard';
import { formatMoney } from '@/lib/format';

type MobileChartProps = {
    year: number;
    previousYear: number;
    months: ChartMonth[];
    /** Aktuální měsíc (1–12): sloupce jsou jen do něj včetně. */
    currentMonth: number;
    strongest: { month: number; amount: string } | null;
};

/**
 * Kompaktní graf na mobilu (artboard „Přehled — mobil“): jen letošní sloupce do aktuálního měsíce,
 * výška 96 px, aktuální měsíc v akcentu. Klepnutí na sloupec ukáže tooltip s oběma částkami.
 */
export default function MobileChart({
    year,
    previousYear,
    months,
    currentMonth,
    strongest,
}: MobileChartProps) {
    const [active, setActive] = useState<number | null>(null);
    const visible = months.slice(0, currentMonth);
    const max = Math.max(1, ...visible.map((m) => Number(m.current ?? 0)));
    const hovered = active === null ? null : visible[active];

    const summary = [
        `Fakturace po měsících ${year}, v tisících Kč.`,
        strongest
            ? `Nejsilnější měsíc je ${MONTHS[strongest.month - 1]} s ${formatMoney(strongest.amount)}.`
            : 'Zatím nic nevyfakturováno.',
    ].join(' ');

    return (
        <div className="relative">
            <div role="img" aria-label={summary}>
                <div
                    className="border-separator grid h-24 items-end gap-1.5 border-0 border-b border-solid"
                    style={{ gridTemplateColumns: `repeat(${visible.length}, minmax(0, 1fr))` }}
                >
                    {visible.map((m, index) => {
                        const height = Math.max(2, Math.round((Number(m.current ?? 0) / max) * 92));

                        return (
                            <button
                                key={m.month}
                                type="button"
                                tabIndex={-1}
                                aria-hidden="true"
                                title={`${MONTHS[index]} ${formatMoney(m.current ?? 0)}`}
                                className="cursor-pointer rounded-t-[5px] rounded-b-[2px] border-0 p-0"
                                style={{
                                    height,
                                    background:
                                        index === currentMonth - 1
                                            ? 'var(--accent)'
                                            : 'var(--fill-strong)',
                                    outline: active === index ? '2px solid var(--accent)' : 'none',
                                }}
                                onClick={() => setActive(active === index ? null : index)}
                            />
                        );
                    })}
                </div>
                <div
                    className="text-label-3 mt-3 grid gap-1.5 text-center text-[10px] leading-3"
                    style={{ gridTemplateColumns: `repeat(${visible.length}, minmax(0, 1fr))` }}
                    aria-hidden="true"
                >
                    {visible.map((m, index) => (
                        <span key={m.month}>{MONTHS_SHORT[index].charAt(0)}</span>
                    ))}
                </div>
            </div>

            {hovered && (
                <div
                    role="presentation"
                    className="af-glass pointer-events-none absolute top-0 left-1/2 z-10 min-w-[150px] -translate-x-1/2 rounded-[12px] px-3 py-2 text-[12px] leading-4 shadow-[var(--shadow-glass)]"
                >
                    <div className="mb-1 font-semibold capitalize">{MONTHS[hovered.month - 1]}</div>
                    <div className="flex justify-between gap-3">
                        <span>{year}</span>
                        <b className="tabular-nums">{formatMoney(hovered.current ?? 0)}</b>
                    </div>
                    <div className="text-label-2 flex justify-between gap-3">
                        <span>{previousYear}</span>
                        <span className="tabular-nums">{formatMoney(hovered.previous)}</span>
                    </div>
                </div>
            )}

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
                    {visible.map((m) => (
                        <tr key={m.month}>
                            <th scope="row">{MONTHS[m.month - 1]}</th>
                            <td>{formatMoney(m.current ?? 0)}</td>
                            <td>{formatMoney(m.previous)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
