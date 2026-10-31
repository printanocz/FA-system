import { Link } from '@inertiajs/react';
import { Send } from 'lucide-react';
import type { OverdueRow } from '@/lib/dashboard';
import { formatDate, formatMoney, plural } from '@/lib/format';

type OverdueListProps = {
    rows: OverdueRow[];
    total: number;
    loadingId: number | null;
    onRemind: (row: OverdueRow) => void;
};

/** Faktury po splatnosti (nejvýš pět, nejdéle po splatnosti první) s tlačítkem „Odeslat upomínku“. */
export default function OverdueList({ rows, total, loadingId, onRemind }: OverdueListProps) {
    if (rows.length === 0) return null;

    return (
        <section aria-labelledby="overdue-title" className="flex flex-col gap-2">
            <div className="af-list-header !p-0 flex items-baseline justify-between">
                <h2 id="overdue-title" className="m-0 text-[13px] leading-[18px] font-normal">
                    Po splatnosti
                </h2>
                {total > rows.length && (
                    <Link
                        href="/faktury?stav=po-splatnosti"
                        className="text-accent-text no-underline"
                    >
                        Všechny ({total})
                    </Link>
                )}
            </div>
            <div className="af-list">
                {rows.map((row) => (
                    <div key={row.id} className="af-list__row items-center gap-3">
                        <Link
                            href={`/faktury/${row.id}`}
                            className="text-label flex min-w-0 flex-1 flex-col gap-0.5 no-underline"
                        >
                            <span className="truncate font-semibold">{row.contact ?? '—'}</span>
                            <span className="text-label-3 text-xs leading-4">
                                <span className="af-mono !text-xs">{row.number ?? '—'}</span> ·
                                splatnost {formatDate(row.due_date, false)}
                            </span>
                        </Link>
                        <div className="flex shrink-0 flex-col items-end gap-0.5">
                            <span className="font-semibold tabular-nums">
                                {formatMoney(row.remaining, row.currency)}
                            </span>
                            <span className="af-badge af-badge--red whitespace-nowrap">
                                {row.overdue_days} {plural(row.overdue_days, 'den', 'dny', 'dní')}
                            </span>
                        </div>
                        <button
                            type="button"
                            className="af-btn af-btn--sm af-btn--tinted max-tablet:af-btn--icon cursor-pointer"
                            disabled={loadingId === row.id}
                            aria-label={`Odeslat upomínku: ${row.contact ?? row.number ?? ''}`}
                            onClick={() => onRemind(row)}
                        >
                            <Send strokeWidth={1.75} aria-hidden="true" />
                            <span className="max-tablet:hidden">Odeslat upomínku</span>
                        </button>
                    </div>
                ))}
            </div>
        </section>
    );
}
