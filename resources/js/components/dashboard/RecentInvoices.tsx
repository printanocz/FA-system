import { Link } from '@inertiajs/react';
import { documentBadge } from '@/lib/documents';
import type { RecentInvoice } from '@/lib/dashboard';
import { formatDate, formatMoney } from '@/lib/format';

/** Posledních pět faktur: stejné štítky stavu a formáty jako v seznamu faktur. */
export default function RecentInvoices({ rows }: { rows: RecentInvoice[] }) {
    return (
        <section aria-labelledby="recent-title" className="flex flex-col gap-2">
            <div className="af-list-header !p-0 flex items-baseline justify-between">
                <h2 id="recent-title" className="m-0 text-[13px] leading-[18px] font-normal">
                    Poslední doklady
                </h2>
                <Link href="/faktury" className="text-accent-text no-underline">
                    Všechny faktury
                </Link>
            </div>
            <div className="af-list">
                {rows.map((row) => {
                    const badge = documentBadge(row.status, row.computed_status, row.overdue_days);
                    // Na mobilu stejně jako v seznamu: stručný štítek bez počtu dní.
                    const short = documentBadge(row.status, row.computed_status);

                    return (
                        <Link
                            key={row.id}
                            href={`/faktury/${row.id}`}
                            className="af-list__row text-label items-center gap-3 no-underline"
                        >
                            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span className="truncate font-semibold">{row.contact ?? '—'}</span>
                                <span className="text-label-3 text-xs leading-4">
                                    <span className="af-mono !text-xs">{row.number ?? '—'}</span>
                                    {row.issue_date && <> · {formatDate(row.issue_date, false)}</>}
                                </span>
                            </div>
                            <div className="flex shrink-0 flex-col items-end gap-0.5">
                                <span className="font-semibold tabular-nums">
                                    {formatMoney(row.total, row.currency)}
                                </span>
                                <span
                                    className={`${badge.className} max-tablet:hidden whitespace-nowrap`}
                                >
                                    {badge.label}
                                </span>
                                <span
                                    className={`${short.className} tablet:hidden whitespace-nowrap`}
                                >
                                    {short.label}
                                </span>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}
