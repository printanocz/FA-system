import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Download, Ellipsis, FileText, Plus, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ConfirmSheet from '@/components/ConfirmSheet';
import InvoicePreview from '@/components/invoices/InvoicePreview';
import PaymentSheet from '@/components/invoices/PaymentSheet';
import Menu from '@/components/Menu';
import Toolbar from '@/components/Toolbar';
import { useFlash } from '@/hooks/useFlash';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { MOBILE_QUERY } from '@/lib/breakpoints';
import { documentBadge, documentTypeLabel } from '@/lib/documents';
import { formatDate, formatDateTime, formatMoney, moneyParts, plural } from '@/lib/format';
import { diffDays } from '@/lib/invoice';
import {
    paymentMethodLabel,
    pdfUrl,
    type InvoiceDetail,
    type InvoiceShowProps,
} from '@/lib/invoices';

const todayIso = () => {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

/** Věta pod ukazatelem: kdy je splatnost, nebo kdy byla faktura uhrazena. */
function DueLine({ invoice }: { invoice: InvoiceDetail }) {
    const paid = `uhrazeno ${formatMoney(invoice.paid_total, invoice.currency)}`;

    if (invoice.status === 'paid') {
        return <>Uhrazeno{invoice.paid_at ? ` ${formatDate(invoice.paid_at)}` : ''}</>;
    }

    if (invoice.computed_status === 'overdue' && invoice.overdue_days) {
        return (
            <>
                <b className="is-down">
                    Po splatnosti {invoice.overdue_days}{' '}
                    {plural(invoice.overdue_days, 'den', 'dny', 'dní')}
                </b>
                <span>
                    od {formatDate(invoice.due_date)} · {paid}
                </span>
            </>
        );
    }

    const days = diffDays(todayIso(), invoice.due_date);
    const when =
        days === 0
            ? 'dnes'
            : days === 1
              ? 'zítra'
              : `za ${days} ${plural(days, 'den', 'dny', 'dní')}`;

    return (
        <>
            Splatnost {when}, {formatDate(invoice.due_date)} · {paid}
        </>
    );
}

/** Po akci se obnoví i počet po splatnosti v Sidebaru a hlášky (sdílené props). */
const AFTER_ACTION = ['invoice', 'contact', 'related', 'overdueInvoices', 'status', 'error'];

export default function InvoiceShow({ invoice, contact, related }: InvoiceShowProps) {
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const flash = useFlash();
    const [paying, setPaying] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [removePayment, setRemovePayment] = useState<InvoiceDetail['payments'][number] | null>(
        null,
    );
    const [busy, setBusy] = useState(false);

    const badge = documentBadge(invoice.status, invoice.computed_status, invoice.overdue_days);
    const money = moneyParts(invoice.remaining, invoice.currency);
    const overdue = invoice.computed_status === 'overdue';

    const run = (method: 'post' | 'delete', url: string) => {
        setBusy(true);
        router[method](
            url,
            {},
            {
                preserveScroll: true,
                only: AFTER_ACTION,
                onSuccess: (page) => flash.handle(page),
                onFinish: () => setBusy(false),
            },
        );
    };

    const timeline: { label: string; detail: string; done: boolean }[] = [
        {
            label: 'Vystaveno',
            detail: invoice.issued_at
                ? formatDateTime(invoice.issued_at)
                : formatDate(invoice.issue_date),
            done: true,
        },
    ];
    if (invoice.sent_at) {
        timeline.push({
            label: 'Odesláno e-mailem',
            detail: formatDateTime(invoice.sent_at),
            done: true,
        });
    }
    timeline.push({
        label: 'Splatnost',
        detail: formatDate(invoice.due_date),
        done: diffDays(todayIso(), invoice.due_date) <= 0,
    });

    return (
        <>
            <Head title={`Faktura ${invoice.number}`} />

            <Toolbar
                title={`Faktura ${invoice.number}`}
                back={{ href: '/faktury', label: 'Faktury vydané' }}
                meta={<span className={badge.className}>{badge.label}</span>}
            >
                <div className="af-toolbar__group af-glass">
                    <a
                        href={pdfUrl(invoice.id, true)}
                        download
                        className="af-btn af-btn--icon"
                        aria-label="Stáhnout PDF"
                        title="Stáhnout PDF"
                    >
                        <Download strokeWidth={1.75} aria-hidden="true" />
                    </a>
                    <button
                        type="button"
                        className="af-btn af-btn--icon"
                        aria-label="Odeslat"
                        title="Přijde s odesíláním"
                        disabled
                    >
                        <Send strokeWidth={1.75} aria-hidden="true" />
                    </button>
                    <Menu
                        label="Další akce"
                        className="af-btn af-btn--icon cursor-pointer"
                        items={[
                            {
                                label: 'Kopírovat jako novou',
                                onSelect: () => run('post', `/faktury/${invoice.id}/kopie`),
                            },
                            ...(invoice.can_delete
                                ? [
                                      {
                                          label: 'Smazat',
                                          destructive: true,
                                          separatorBefore: true,
                                          onSelect: () => setDeleting(true),
                                      },
                                  ]
                                : []),
                        ]}
                    >
                        <Ellipsis strokeWidth={2} aria-hidden="true" />
                    </Menu>
                </div>
                {invoice.can_pay && (
                    <button
                        type="button"
                        className={`af-btn cursor-pointer ${isMobile ? 'af-btn--tinted' : 'af-btn--prominent'}`}
                        onClick={() => setPaying(true)}
                    >
                        <Plus strokeWidth={2} aria-hidden="true" />
                        Přidat úhradu
                    </button>
                )}
            </Toolbar>

            {flash.alert && (
                <div
                    role="alert"
                    className="bg-red-soft text-red flex items-start justify-between gap-3 rounded-[14px] px-4 py-3 text-[14px] leading-5"
                >
                    <span>
                        {flash.alert.message}
                        {flash.alert.href && (
                            <>
                                {' '}
                                <Link
                                    href={flash.alert.href}
                                    className="font-semibold text-current"
                                >
                                    {flash.alert.label ?? 'Otevřít'}
                                </Link>
                            </>
                        )}
                    </span>
                    <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 font-semibold text-current"
                        onClick={flash.dismiss}
                    >
                        Zavřít
                    </button>
                </div>
            )}

            <div className="flex flex-wrap items-start gap-6">
                <div className="max-tablet:order-last w-full max-w-[572px] min-w-0 flex-[0_1_572px]">
                    <InvoicePreview invoice={invoice} />
                    <a
                        href={pdfUrl(invoice.id)}
                        target="_blank"
                        rel="noopener"
                        className="af-btn mt-3"
                    >
                        <FileText strokeWidth={1.75} aria-hidden="true" />
                        Otevřít PDF
                    </a>
                    {invoice.pdf_sha256 && (
                        <p className="text-label-2 mt-2 mb-0 text-[13px] leading-[18px]">
                            Archivní PDF · SHA-256{' '}
                            <span className="af-mono" title={invoice.pdf_sha256}>
                                {invoice.pdf_sha256.slice(0, 12)}…
                            </span>
                        </p>
                    )}
                </div>

                <aside
                    className="flex min-w-0 flex-[1_1_320px] flex-col gap-4"
                    aria-label="Stav a údaje faktury"
                >
                    <div className="af-card af-kpi">
                        <p className="af-card__title">Zbývá uhradit</p>
                        <div className="af-kpi__value">
                            {money.number} <small>{money.symbol}</small>
                        </div>
                        <div
                            className={`af-meter ${overdue ? 'af-meter--warn' : ''}`}
                            role="img"
                            aria-label={`Uhrazeno ${invoice.paid_percent} procent`}
                        >
                            <span style={{ width: `${invoice.paid_percent}%` }} />
                        </div>
                        <div className="af-kpi__delta">
                            <DueLine invoice={invoice} />
                        </div>
                    </div>

                    <div>
                        <div className="af-list-header">Úhrady</div>
                        <div className="af-list">
                            {invoice.payments.length === 0 ? (
                                <div className="af-list__row">
                                    <span className="af-list__label text-label-3">
                                        Zatím žádná úhrada
                                    </span>
                                    {invoice.can_pay && (
                                        <button
                                            type="button"
                                            className="af-btn af-btn--sm af-btn--tinted cursor-pointer"
                                            onClick={() => setPaying(true)}
                                        >
                                            Přidat úhradu
                                        </button>
                                    )}
                                </div>
                            ) : (
                                invoice.payments.map((payment) => (
                                    <div key={payment.id} className="af-list__row">
                                        <span className="af-list__label flex flex-col">
                                            <span className="font-semibold tabular-nums">
                                                {formatMoney(payment.amount, invoice.currency)}
                                            </span>
                                            <span className="text-label-3 text-xs leading-4">
                                                {formatDate(payment.paid_at)} ·{' '}
                                                {paymentMethodLabel(payment.method)}
                                                {payment.note && ` · ${payment.note}`}
                                            </span>
                                        </span>
                                        {payment.deletable && (
                                            <button
                                                type="button"
                                                className="af-btn af-btn--icon af-btn--sm cursor-pointer"
                                                aria-label={`Smazat úhradu ${formatMoney(payment.amount, invoice.currency)}`}
                                                onClick={() => setRemovePayment(payment)}
                                            >
                                                <Trash2 strokeWidth={1.75} aria-hidden="true" />
                                            </button>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div>
                        <div className="af-list-header">Průběh</div>
                        <div className="af-list p-4">
                            <div className="tl">
                                {timeline.map((step) => (
                                    <TimelineStep key={step.label} {...step} />
                                ))}
                            </div>
                        </div>
                    </div>

                    <div>
                        <div className="af-list-header">Odběratel</div>
                        <div className="af-list">
                            {contact ? (
                                <Link
                                    href={`/adresar/${contact.id}`}
                                    className="af-list__row text-label no-underline"
                                >
                                    <span className="af-list__label font-semibold">
                                        {contact.name}
                                    </span>
                                    <ChevronRight
                                        className="af-list__chevron"
                                        strokeWidth={2}
                                        aria-hidden="true"
                                    />
                                </Link>
                            ) : (
                                <div className="af-list__row">
                                    <span className="af-list__label font-semibold">
                                        {invoice.buyer?.name ?? '—'}
                                    </span>
                                </div>
                            )}
                            {(contact?.ico ?? invoice.buyer?.ico) && (
                                <div className="af-list__row">
                                    <span className="af-list__label">IČ</span>
                                    <span className="af-list__value af-mono">
                                        {contact?.ico ?? invoice.buyer?.ico}
                                    </span>
                                </div>
                            )}
                            {contact && (
                                <div className="af-list__row">
                                    <span className="af-list__label">
                                        Obrat {contact.stats.year}
                                    </span>
                                    <span className="af-list__value">
                                        {formatMoney(contact.stats.turnover)} ·{' '}
                                        {contact.stats.invoice_count}{' '}
                                        {plural(
                                            contact.stats.invoice_count,
                                            'faktura',
                                            'faktury',
                                            'faktur',
                                        )}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {related.length > 0 && (
                        <div>
                            <div className="af-list-header">Související doklady</div>
                            <div className="af-list">
                                {related.map((document) => {
                                    const relatedBadge = documentBadge(document.status, null);
                                    const content = (
                                        <>
                                            <span className="af-list__label">
                                                {documentTypeLabel(document.type)}{' '}
                                                <span className="af-mono">
                                                    {document.number ?? ''}
                                                </span>
                                            </span>
                                            <span className={relatedBadge.className}>
                                                {relatedBadge.label}
                                            </span>
                                        </>
                                    );

                                    return document.url ? (
                                        <Link
                                            key={document.id}
                                            href={document.url}
                                            className="af-list__row text-label no-underline"
                                        >
                                            {content}
                                        </Link>
                                    ) : (
                                        <div key={document.id} className="af-list__row">
                                            {content}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    <div className="af-list">
                        <button
                            type="button"
                            className="af-list__row text-accent-text w-full cursor-pointer border-0 bg-transparent text-left"
                            style={{ font: 'inherit' }}
                            disabled={busy}
                            onClick={() => run('post', `/faktury/${invoice.id}/kopie`)}
                        >
                            Kopírovat jako novou fakturu
                        </button>
                    </div>
                </aside>
            </div>

            <PaymentSheet
                open={paying}
                invoiceId={invoice.id}
                remaining={invoice.remaining}
                currency={invoice.currency}
                onClose={() => setPaying(false)}
                onDone={flash.handle}
            />

            <ConfirmSheet
                open={removePayment !== null}
                title="Smazat úhradu?"
                text={
                    removePayment
                        ? `Úhrada ${formatMoney(removePayment.amount, invoice.currency)} z ${formatDate(removePayment.paid_at)} se odstraní a stav faktury se přepočítá.`
                        : ''
                }
                confirmLabel="Smazat úhradu"
                destructive
                busy={busy}
                onCancel={() => setRemovePayment(null)}
                onConfirm={() => {
                    if (removePayment) {
                        run('delete', `/faktury/${invoice.id}/uhrady/${removePayment.id}`);
                    }
                    setRemovePayment(null);
                }}
            />

            <ConfirmSheet
                open={deleting}
                title={`Smazat fakturu ${invoice.number}?`}
                text="Číslo se vrátí do řady, aby v číslování nevznikla díra. Smazání nejde vrátit."
                confirmLabel="Smazat fakturu"
                destructive
                busy={busy}
                onCancel={() => setDeleting(false)}
                onConfirm={() => {
                    setDeleting(false);
                    run('delete', `/faktury/${invoice.id}`);
                }}
            />
            {flash.toast}
        </>
    );
}

function TimelineStep({ label, detail, done }: { label: string; detail: string; done: boolean }) {
    return (
        <>
            <span className={`tl__dot ${done ? 'tl__dot--done' : ''}`} aria-hidden="true" />
            <div>
                <div className={done ? 'font-semibold' : 'text-label-2 font-semibold'}>{label}</div>
                <div className="text-label-3 text-xs leading-4">{detail}</div>
            </div>
        </>
    );
}
