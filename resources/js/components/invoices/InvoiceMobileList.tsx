import { Link } from '@inertiajs/react';
import { documentBadge } from '@/lib/documents';
import { formatDate, formatMoney } from '@/lib/format';
import type { InvoiceRow } from '@/lib/invoices';
import { invoiceHref } from './InvoiceTable';

/** Seznam faktur na mobilu (artboard „Faktury na mobilu“): odběratel a částka, číslo s datem a stav. */
export default function InvoiceMobileList({ rows }: { rows: InvoiceRow[] }) {
    return (
        <div className="af-list">
            {rows.map((row) => {
                // Na mobilu je štítek stručný: bez počtu dní a „Částečně“ místo „Částečně uhrazeno“.
                const full = documentBadge(row.status, row.computed_status);
                const label =
                    row.status === 'partially_paid' && !row.computed_status
                        ? 'Částečně'
                        : full.label;

                return (
                    <Link
                        key={row.id}
                        href={invoiceHref(row)}
                        className="af-list__row text-label items-start gap-2.5 no-underline"
                    >
                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                            <div className="flex justify-between gap-2">
                                <span className="truncate font-semibold">{row.contact ?? '—'}</span>
                                <span className="font-semibold whitespace-nowrap tabular-nums">
                                    {formatMoney(row.total, row.currency)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-label-3 text-xs leading-4">
                                    <span className="af-mono !text-xs">{row.number ?? '—'}</span>
                                    {row.issue_date && <> · {formatDate(row.issue_date, false)}</>}
                                </span>
                                <span className={`${full.className} whitespace-nowrap`}>
                                    {label}
                                </span>
                            </div>
                        </div>
                    </Link>
                );
            })}
        </div>
    );
}
