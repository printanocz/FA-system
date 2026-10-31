import { Link } from '@inertiajs/react';
import { Send } from 'lucide-react';
import type { OverdueRow } from '@/lib/dashboard';
import { formatDate, formatMoney, plural } from '@/lib/format';

type OverdueProps = {
    rows: OverdueRow[];
    total: number;
    loadingId: number | null;
    onRemind: (row: OverdueRow) => void;
};

const ALL_HREF = '/faktury?stav=po-splatnosti';

function allLabel(total: number) {
    return `Všechny po splatnosti (${total})`;
}

/** Desktop: karta „Po splatnosti“ v pravém sloupci vedle grafu. Bez faktur po splatnosti se nevykreslí. */
export function OverdueCard({ rows, total, loadingId, onRemind }: OverdueProps) {
    if (rows.length === 0) return null;
    const single = total === 1 && rows.length === 1;

    return (
        <div className="af-card flex flex-col gap-3.5">
            <div className="flex items-baseline justify-between">
                <h2 className="af-card__title">Po splatnosti</h2>
                <span className="af-badge af-badge--red">{total}</span>
            </div>

            {rows.map((row) => (
                <div key={row.id} className="flex items-center gap-3">
                    <Link
                        href={`/faktury/${row.id}`}
                        className="text-label min-w-0 flex-1 no-underline"
                    >
                        <div className="truncate font-semibold">{row.contact ?? '—'}</div>
                        <div className="text-label-3 text-xs leading-4">
                            <span className="af-mono !text-xs">{row.number ?? '—'}</span> ·
                            splatnost {formatDate(row.due_date, false)}
                        </div>
                    </Link>
                    <div className="text-right font-semibold whitespace-nowrap tabular-nums">
                        {formatMoney(row.remaining, row.currency)}
                    </div>
                    {!single && (
                        <button
                            type="button"
                            className="af-btn af-btn--sm af-btn--tinted af-btn--icon cursor-pointer"
                            disabled={loadingId === row.id}
                            aria-label={`Odeslat upomínku – ${row.contact ?? row.number ?? ''}`}
                            onClick={() => onRemind(row)}
                        >
                            <Send strokeWidth={1.75} aria-hidden="true" />
                        </button>
                    )}
                </div>
            ))}

            {single ? (
                <button
                    type="button"
                    className="af-btn af-btn--tinted cursor-pointer self-start"
                    disabled={loadingId === rows[0].id}
                    onClick={() => onRemind(rows[0])}
                >
                    Odeslat upomínku
                </button>
            ) : (
                <Link href={ALL_HREF} className="text-accent-text text-[14px] no-underline">
                    {allLabel(total)}
                </Link>
            )}
        </div>
    );
}

/** Mobil: `af-list-header` + `af-list`, u jedné faktury tlačítko na plnou šířku pod řádkem. */
export function OverdueList({ rows, total, loadingId, onRemind }: OverdueProps) {
    if (rows.length === 0) return null;
    const single = total === 1 && rows.length === 1;

    return (
        <section aria-labelledby="overdue-title">
            <h2 id="overdue-title" className="af-list-header m-0 font-normal">
                Po splatnosti
            </h2>
            <div className="af-list">
                {rows.map((row) => (
                    <div key={row.id} className="af-list__row items-center gap-3">
                        <Link
                            href={`/faktury/${row.id}`}
                            className="af-list__label text-label no-underline"
                        >
                            <div className="truncate font-semibold">{row.contact ?? '—'}</div>
                            <div className="text-label-3 text-xs leading-4">
                                <span className="af-mono !text-xs">{row.number ?? '—'}</span> ·{' '}
                                {row.overdue_days} {plural(row.overdue_days, 'den', 'dny', 'dní')}{' '}
                                po splatnosti
                            </div>
                        </Link>
                        <span className="font-semibold tabular-nums">
                            {formatMoney(row.remaining, row.currency)}
                        </span>
                        {!single && (
                            <button
                                type="button"
                                className="af-btn af-btn--sm af-btn--tinted af-btn--icon cursor-pointer"
                                disabled={loadingId === row.id}
                                aria-label={`Odeslat upomínku – ${row.contact ?? row.number ?? ''}`}
                                onClick={() => onRemind(row)}
                            >
                                <Send strokeWidth={1.75} aria-hidden="true" />
                            </button>
                        )}
                    </div>
                ))}
                {single ? (
                    <div className="af-list__row">
                        <button
                            type="button"
                            className="af-btn af-btn--tinted w-full cursor-pointer"
                            disabled={loadingId === rows[0].id}
                            onClick={() => onRemind(rows[0])}
                        >
                            Odeslat upomínku
                        </button>
                    </div>
                ) : (
                    <div className="af-list__row">
                        <Link href={ALL_HREF} className="text-accent-text text-[14px] no-underline">
                            {allLabel(total)}
                        </Link>
                    </div>
                )}
            </div>
        </section>
    );
}
