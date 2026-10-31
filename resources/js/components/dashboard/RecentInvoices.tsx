import { Link } from '@inertiajs/react';
import type { RecentInvoice } from '@/lib/dashboard';
import { documentBadge } from '@/lib/documents';
import { formatDate, formatPrice } from '@/lib/format';

/** Poslední doklady (jen desktop a tablet): `af-card` přes celou šířku s `af-table`, stejné štítky jako v seznamu faktur. */
export default function RecentInvoices({ rows }: { rows: RecentInvoice[] }) {
    return (
        <section className="af-card flex flex-col !p-2" aria-labelledby="recent-title">
            <div className="flex items-center justify-between px-4 pt-3.5 pb-1.5">
                <h2
                    id="recent-title"
                    className="m-0 text-[22px] leading-7 font-bold tracking-[-0.2px]"
                >
                    Poslední doklady
                </h2>
                <Link href="/faktury" className="af-btn af-btn--plain no-underline">
                    Všechny faktury
                </Link>
            </div>
            <div className="af-table-wrap">
                <table className="af-table">
                    <thead>
                        <tr>
                            <th>Číslo</th>
                            <th>Odběratel</th>
                            <th>Vystaveno</th>
                            <th>Splatnost</th>
                            <th className="is-num">Částka</th>
                            <th>Stav</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => {
                            const badge = documentBadge(
                                row.status,
                                row.computed_status,
                                row.overdue_days,
                            );

                            return (
                                <tr key={row.id}>
                                    <td>
                                        <Link
                                            href={`/faktury/${row.id}`}
                                            className="af-mono text-label no-underline"
                                        >
                                            {row.number ?? '—'}
                                        </Link>
                                    </td>
                                    <td>
                                        <div className="af-cell-main">{row.contact ?? '—'}</div>
                                        {row.description && (
                                            <div className="af-cell-sub">{row.description}</div>
                                        )}
                                    </td>
                                    <td>{row.issue_date ? formatDate(row.issue_date) : '—'}</td>
                                    <td
                                        className={
                                            row.computed_status === 'overdue' ? 'is-overdue' : ''
                                        }
                                    >
                                        {row.due_date ? formatDate(row.due_date) : '—'}
                                    </td>
                                    <td className="is-num">
                                        {formatPrice(row.total, row.currency)}
                                    </td>
                                    <td>
                                        <span className={badge.className}>{badge.label}</span>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
