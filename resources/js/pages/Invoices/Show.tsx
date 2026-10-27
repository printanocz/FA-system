import { Head, Link, usePage } from '@inertiajs/react';
import Toolbar from '@/components/Toolbar';
import { documentBadge } from '@/lib/documents';
import { formatDate, formatMoney } from '@/lib/format';
import { formatDecimal } from '@/lib/invoice';

type ShowProps = {
    document: {
        id: number;
        number: string | null;
        status: string;
        computed_status: string | null;
        contact: string | null;
        issue_date: string | null;
        due_date: string | null;
        vs: string | null;
        currency: string;
        subtotal: string;
        rounding: string;
        total: string;
        editable: boolean;
        items: {
            id: number;
            name: string;
            qty: string;
            unit: string;
            unit_price: string;
            line_total: string;
        }[];
    };
};

type Flash = {
    status: string | null;
    error: string | { message: string } | null;
};

/** Dočasný detail dokladu jen pro čtení. Nahradí ho obrazovka z T-09. */
export default function InvoiceShow({ document }: ShowProps) {
    const { status, error } = usePage<Flash>().props;
    const badge = documentBadge(document.status, document.computed_status);
    const message = typeof error === 'string' ? error : error?.message;

    return (
        <>
            <Head title={document.number ?? 'Koncept faktury'} />

            <Toolbar
                title={document.number ?? 'Koncept faktury'}
                back={{ href: '/', label: 'Přehled' }}
                meta={<span className={badge.className}>{badge.label}</span>}
            >
                {document.editable && (
                    <Link href={`/faktury/${document.id}/upravit`} className="af-btn no-underline">
                        Upravit
                    </Link>
                )}
            </Toolbar>

            {status && (
                <p className="text-label-2 m-0 text-[14px]" role="status">
                    {status}
                </p>
            )}
            {message && (
                <div
                    role="alert"
                    className="bg-red-soft text-red rounded-[14px] px-4 py-3 text-[14px] leading-5"
                >
                    {message}
                </div>
            )}

            <section className="af-card flex flex-col gap-4">
                <dl className="m-0 grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-4 text-[14px]">
                    <Field label="Odběratel">{document.contact ?? '—'}</Field>
                    <Field label="Vystaveno">
                        {document.issue_date ? formatDate(document.issue_date) : '—'}
                    </Field>
                    <Field label="Splatnost">
                        {document.due_date ? formatDate(document.due_date) : '—'}
                    </Field>
                    <Field label="Variabilní symbol">
                        <span className="af-mono">{document.vs ?? '—'}</span>
                    </Field>
                </dl>

                <div className="af-table-wrap">
                    <table className="af-table">
                        <thead>
                            <tr>
                                <th>Popis</th>
                                <th className="is-num">Počet</th>
                                <th className="is-num">Cena za MJ</th>
                                <th className="is-num">Celkem</th>
                            </tr>
                        </thead>
                        <tbody>
                            {document.items.map((item) => (
                                <tr key={item.id}>
                                    <td>{item.name}</td>
                                    <td className="is-num">
                                        {item.qty} {item.unit}
                                    </td>
                                    <td className="is-num">{formatDecimal(item.unit_price)}</td>
                                    <td className="is-num">{formatDecimal(item.line_total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-baseline justify-between gap-4 border-t border-separator pt-3">
                    <span className="text-label-2">Celkem k úhradě</span>
                    <span className="text-[22px] font-semibold tabular-nums">
                        {formatMoney(document.total, document.currency)}
                    </span>
                </div>
            </section>
        </>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <dt className="text-label-3 text-xs">{label}</dt>
            <dd className="m-0">{children}</dd>
        </div>
    );
}
