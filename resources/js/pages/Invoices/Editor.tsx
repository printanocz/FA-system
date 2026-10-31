import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileCheck, Send } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import ConfirmSheet from '@/components/ConfirmSheet';
import CustomerSection from '@/components/invoice/CustomerSection';
import ItemsSection from '@/components/invoice/ItemsSection';
import SummaryCard from '@/components/invoice/SummaryCard';
import { Toggle } from '@/components/settings/SettingsRow';
import TextField from '@/components/TextField';
import { useToast } from '@/components/Toast';
import Toolbar from '@/components/Toolbar';
import { useInvoiceTotals } from '@/hooks/useInvoiceTotals';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useUnsavedGuard } from '@/hooks/useUnsavedGuard';
import { MOBILE_QUERY } from '@/lib/breakpoints';
import { pdfUrl } from '@/lib/invoices';
import {
    addDaysIso,
    czToIso,
    diffDays,
    isBlankRow,
    isoToCz,
    serverToForm,
    toInput,
    type BankAccountOption,
    type ContactSummary,
    type InvoiceFormValues,
    type ServerForm,
} from '@/lib/invoice';

type EditorProps = {
    document: { id: number } | null;
    form: ServerForm;
    contact: ContactSummary | null;
    bankAccounts: BankAccountOption[];
    defaults: {
        due_days: number;
        constant_symbol: string;
        round_total: boolean;
        vat_payer: boolean;
    };
};

type SharedFlash = {
    status: string | null;
    error: string | { message: string; href?: string; label?: string } | null;
};

const TERMS = [7, 14, 30];

export default function InvoiceEditor(props: EditorProps) {
    // Po uložení nového konceptu se server přesměruje na /upravit a formulář se načte znovu.
    return <EditorForm key={props.document?.id ?? 'new'} {...props} />;
}

function EditorForm({
    document,
    form,
    contact: initialContact,
    bankAccounts,
    defaults,
}: EditorProps) {
    const { status, error } = usePage<SharedFlash>().props;
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const { show, toast } = useToast();
    const initial = useMemo(() => serverToForm(form), [form]);
    const f = useForm<InvoiceFormValues>(initial);
    const [contact, setContact] = useState<ContactSummary | null>(initialContact);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const guard = useUnsavedGuard(f.isDirty);
    const totals = useInvoiceTotals(f.data);
    const data = f.data;

    const errors = f.errors as Record<string, string | undefined>;
    const errorMessage = typeof error === 'string' ? { message: error } : error;

    useEffect(() => {
        if (status) show(status);
        // Hláška po přesměrování se ukáže jen při načtení editoru.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const prepare = (values: InvoiceFormValues) => ({
        ...values,
        issue_date: czToIso(values.issue_date) ?? values.issue_date,
        due_date: czToIso(values.due_date) ?? values.due_date,
        show_taxable_date: values.show_duzp,
        taxable_date: values.show_duzp
            ? (czToIso(values.taxable_date) ?? values.taxable_date)
            : null,
        items: values.items
            .filter((row) => !isBlankRow(row))
            .map((row) => ({
                name: row.name,
                description: row.description,
                qty: row.qty,
                unit: row.unit,
                unit_price: row.unit_price,
                price_item_id: row.price_item_id,
            })),
    });

    const submit = (kind: 'save' | 'issue' | 'issue-send') => {
        if (f.processing) return;
        f.transform(prepare);

        const base = document ? `/faktury/${document.id}` : '/faktury';
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                f.setDefaults();
                if (kind === 'save' && document) show('Koncept uložen');
            },
        };

        if (kind === 'issue' || kind === 'issue-send') {
            if (kind === 'issue-send') f.transform((data) => ({ ...prepare(data), send: true }));
            f.post(document ? `${base}/vystavit` : '/faktury/vystavit', options);
        } else if (document) {
            f.put(base, { ...options, preserveState: true });
        } else {
            f.post(base, options);
        }
    };

    /**
     * Náhled PDF v novém okně. Uložený koncept bez změn se otevře hned, jinak se nejdřív uloží.
     * Okno se otevře ještě v kliknutí (jinak ho prohlížeč zablokuje) a po uložení se přesměruje.
     */
    const previewPdf = () => {
        if (f.processing) return;
        const win = window.open('', '_blank');
        const open = (id: number) => {
            if (win) win.location.href = pdfUrl(id);
            else window.open(pdfUrl(id), '_blank');
        };

        if (document && !f.isDirty) {
            open(document.id);
            return;
        }

        f.transform(prepare);
        const options = {
            preserveScroll: true,
            onSuccess: (page: { props: Record<string, unknown> }) => {
                f.setDefaults();
                const saved = page.props.document as { id: number } | null | undefined;
                const id = document?.id ?? saved?.id;
                if (id) open(id);
                else win?.close();
            },
            onError: () => win?.close(),
        };

        if (document) f.put(`/faktury/${document.id}`, { ...options, preserveState: true });
        else f.post('/faktury', options);
    };

    // ⌘/Ctrl+S uloží koncept, ⌘/Ctrl+Enter vystaví. Handler vždy vidí poslední stav formuláře.
    const latest = useRef(submit);
    useEffect(() => {
        latest.current = submit;
    });
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (!(event.metaKey || event.ctrlKey)) return;
            if (event.key.toLowerCase() === 's') {
                event.preventDefault();
                latest.current('save');
            } else if (event.key === 'Enter') {
                event.preventDefault();
                latest.current('issue');
            }
        };
        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const pickContact = (next: ContactSummary | null) => {
        setContact(next);
        f.setData((current) => {
            if (!next) return { ...current, contact_id: null };

            const issue = czToIso(current.issue_date);
            const days = next.default_due_days ?? defaults.due_days;

            return {
                ...current,
                contact_id: next.id,
                due_date: issue ? isoToCz(addDaysIso(issue, days)) : current.due_date,
                discount_percent: toInput(String(Number(next.default_discount_percent ?? 0))),
            };
        });
    };

    const changeIssue = (value: string) => {
        f.setData((current) => {
            const before = czToIso(current.issue_date);
            const after = czToIso(value);
            const due = czToIso(current.due_date);
            // Délka splatnosti se při posunu data vystavení zachová.
            const due_date =
                before && after && due
                    ? isoToCz(addDaysIso(after, diffDays(before, due)))
                    : current.due_date;

            return { ...current, issue_date: value, due_date };
        });
    };

    const normalizeDate = (field: 'issue_date' | 'due_date' | 'taxable_date') => {
        const iso = czToIso(data[field]);
        if (iso && isoToCz(iso) !== data[field]) f.setData(field, isoToCz(iso));
    };

    const issueIso = czToIso(data.issue_date);
    const dueIso = czToIso(data.due_date);
    const term = issueIso && dueIso ? diffDays(issueIso, dueIso) : null;

    const account =
        bankAccounts.find((candidate) => candidate.id === data.bank_account_id) ??
        bankAccounts[0] ??
        null;

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <>
            <Head title={document ? 'Upravit fakturu' : 'Nová faktura'} />

            <Toolbar
                title={document ? 'Koncept faktury' : 'Nová faktura'}
                back={{ href: '/faktury', label: 'Faktury vydané' }}
                meta={<span className="af-badge af-badge--outline">Koncept</span>}
            >
                <button
                    type="button"
                    className="af-btn"
                    disabled={f.processing}
                    onClick={() => submit('save')}
                >
                    Uložit koncept
                </button>
                <button
                    type="button"
                    className="af-btn"
                    disabled={f.processing}
                    onClick={previewPdf}
                >
                    Náhled PDF
                </button>
                <button
                    type="button"
                    className="af-btn af-btn--tinted"
                    disabled={f.processing}
                    onClick={() => submit('issue')}
                >
                    <FileCheck strokeWidth={1.75} aria-hidden="true" />
                    Vystavit
                </button>
                <button
                    type="button"
                    className={`af-btn ${isMobile ? 'af-btn--tinted' : 'af-btn--prominent'}`}
                    disabled={f.processing}
                    onClick={() => submit('issue-send')}
                >
                    <Send strokeWidth={1.75} aria-hidden="true" />
                    Vystavit a odeslat
                </button>
            </Toolbar>

            {errorMessage && (
                <div
                    role="alert"
                    className="bg-red-soft text-red flex flex-wrap items-center gap-x-4 gap-y-1 rounded-[14px] px-4 py-3 text-[14px] leading-5"
                >
                    <span>{errorMessage.message}</span>
                    {errorMessage.href && (
                        <Link href={errorMessage.href} className="font-semibold">
                            {errorMessage.label ?? 'Otevřít Nastavení'}
                        </Link>
                    )}
                </div>
            )}
            {hasErrors && (
                <div
                    role="alert"
                    className="bg-red-soft text-red rounded-[14px] px-4 py-3 text-[14px] leading-5"
                >
                    Faktura se neuložila, opravte prosím zvýrazněná pole.
                </div>
            )}

            <div className="flex flex-wrap items-start gap-5">
                <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
                    <CustomerSection
                        contact={contact}
                        error={errors.contact_id}
                        onSelect={pickContact}
                    />

                    <section className="af-card flex flex-col gap-4" aria-labelledby="h-data">
                        <h2 id="h-data" className="m-0 text-[20px] leading-[25px] font-semibold">
                            Data a platba
                        </h2>
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-4">
                            <TextField
                                id="f-issued"
                                label="Datum vystavení"
                                value={data.issue_date}
                                onChange={changeIssue}
                                onBlur={() => normalizeDate('issue_date')}
                                error={errors.issue_date}
                                autoComplete="off"
                            />
                            <div className="af-field">
                                <TextField
                                    id="f-due"
                                    label="Splatnost"
                                    value={data.due_date}
                                    onChange={(value) => f.setData('due_date', value)}
                                    onBlur={() => normalizeDate('due_date')}
                                    error={errors.due_date}
                                    autoComplete="off"
                                />
                                <div
                                    className="af-segmented self-start"
                                    role="group"
                                    aria-label="Délka splatnosti"
                                >
                                    {TERMS.map((days) => (
                                        <button
                                            key={days}
                                            type="button"
                                            aria-pressed={term === days}
                                            disabled={!issueIso}
                                            onClick={() =>
                                                issueIso &&
                                                f.setData(
                                                    'due_date',
                                                    isoToCz(addDaysIso(issueIso, days)),
                                                )
                                            }
                                        >
                                            {days} dní
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <TextField
                                id="f-order"
                                label="Číslo objednávky"
                                value={data.order_number}
                                onChange={(value) => f.setData('order_number', value)}
                                error={errors.order_number}
                                placeholder="Nepovinné"
                                autoComplete="off"
                            />
                            <div className="af-field">
                                <span className="af-field__label" id="f-pay-label">
                                    Způsob úhrady
                                </span>
                                <div
                                    className="af-segmented self-start"
                                    role="group"
                                    aria-labelledby="f-pay-label"
                                >
                                    {(
                                        [
                                            ['bank_transfer', 'Převodem'],
                                            ['cash', 'Hotově'],
                                        ] as const
                                    ).map(([value, label]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            aria-pressed={data.payment_method === value}
                                            onClick={() => f.setData('payment_method', value)}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                                {errors.payment_method && (
                                    <div className="af-field__error" role="alert">
                                        {errors.payment_method}
                                    </div>
                                )}
                            </div>
                            {bankAccounts.length > 1 && data.payment_method === 'bank_transfer' && (
                                <div className="af-field">
                                    <label className="af-field__label" htmlFor="f-account">
                                        Bankovní účet
                                    </label>
                                    <select
                                        id="f-account"
                                        className="af-input"
                                        value={account?.id ?? ''}
                                        onChange={(event) =>
                                            f.setData('bank_account_id', Number(event.target.value))
                                        }
                                    >
                                        {bankAccounts.map((option) => (
                                            <option key={option.id} value={option.id}>
                                                {option.name} · {option.number}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.bank_account_id && (
                                        <div className="af-field__error" role="alert">
                                            {errors.bank_account_id}
                                        </div>
                                    )}
                                </div>
                            )}
                            <TextField
                                id="f-currency"
                                label="Měna"
                                value="CZK"
                                onChange={() => undefined}
                                disabled
                                hint="Další měny přijdou později."
                            />
                        </div>
                        <div className="af-list bg-fill shadow-none">
                            <div className="af-list__row">
                                <span className="af-list__label">
                                    Uvést datum uskutečnění plnění
                                </span>
                                <Toggle
                                    checked={data.show_duzp}
                                    onChange={(checked) => f.setData('show_duzp', checked)}
                                    label="Uvést datum uskutečnění plnění"
                                />
                            </div>
                            {data.show_duzp && (
                                <div className="af-list__row">
                                    <label className="af-list__label" htmlFor="f-duzp">
                                        Datum uskutečnění plnění
                                    </label>
                                    <input
                                        id="f-duzp"
                                        className={`af-input w-40 ${errors.taxable_date ? 'af-input--error' : ''}`}
                                        value={data.taxable_date}
                                        onChange={(event) =>
                                            f.setData('taxable_date', event.target.value)
                                        }
                                        onBlur={() => normalizeDate('taxable_date')}
                                        aria-invalid={errors.taxable_date ? true : undefined}
                                        autoComplete="off"
                                    />
                                </div>
                            )}
                        </div>
                        {errors.taxable_date && (
                            <div className="af-field__error" role="alert">
                                {errors.taxable_date}
                            </div>
                        )}
                    </section>

                    <ItemsSection
                        items={data.items}
                        onChange={(items) => f.setData('items', items)}
                        errors={errors}
                        lineByKey={totals.lineByKey}
                        textBefore={data.text_before}
                        isMobile={isMobile}
                    />

                    <section className="af-card flex flex-col gap-4" aria-labelledby="h-text">
                        <h2 id="h-text" className="m-0 text-[20px] leading-[25px] font-semibold">
                            Texty na faktuře
                        </h2>
                        <TextArea
                            id="f-before"
                            label="Text před položkami"
                            rows={1}
                            value={data.text_before}
                            error={errors.text_before}
                            onChange={(value) => f.setData('text_before', value)}
                        />
                        <TextArea
                            id="f-after"
                            label="Text za položkami"
                            rows={2}
                            value={data.text_after}
                            error={errors.text_after}
                            hint="Výchozí texty se mění v Nastavení › Doklady."
                            onChange={(value) => f.setData('text_after', value)}
                        />
                        <TextArea
                            id="f-note"
                            label="Interní poznámka"
                            rows={2}
                            value={data.note_internal}
                            error={errors.note_internal}
                            hint="Netiskne se na fakturu."
                            onChange={(value) => f.setData('note_internal', value)}
                        />
                    </section>

                    {document && (
                        <div>
                            <button
                                type="button"
                                className="af-btn af-btn--destructive"
                                onClick={() => setConfirmDelete(true)}
                            >
                                Smazat koncept
                            </button>
                        </div>
                    )}
                </div>

                <SummaryCard
                    totals={totals}
                    discount={data.discount_percent}
                    discountError={errors.discount_percent}
                    roundTotal={data.round_total}
                    vatPayer={defaults.vat_payer}
                    paymentMethod={data.payment_method}
                    account={account}
                    constantSymbol={defaults.constant_symbol}
                    onDiscountChange={(value) => f.setData('discount_percent', value)}
                    onRoundChange={(value) => f.setData('round_total', value)}
                />
            </div>

            <ConfirmSheet
                open={guard.pending}
                title="Zahodit neuložené změny?"
                text="Faktura má změny, které nejsou uložené. Když odejdete, přijdete o ně."
                confirmLabel="Zahodit změny"
                cancelLabel="Zůstat"
                destructive
                onConfirm={guard.leave}
                onCancel={guard.stay}
            />
            <ConfirmSheet
                open={confirmDelete}
                title="Smazat koncept?"
                text="Koncept se smaže nadobro."
                confirmLabel="Smazat koncept"
                destructive
                onConfirm={() => document && router.delete(`/faktury/${document.id}`)}
                onCancel={() => setConfirmDelete(false)}
            />
            {toast}
        </>
    );
}

function TextArea({
    id,
    label,
    rows,
    value,
    error,
    hint,
    onChange,
}: {
    id: string;
    label: string;
    rows: number;
    value: string;
    error?: string;
    hint?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="af-field">
            <label className="af-field__label" htmlFor={id}>
                {label}
            </label>
            <textarea
                id={id}
                rows={rows}
                className={`af-input ed-text ${error ? 'af-input--error' : ''}`}
                value={value}
                aria-invalid={error ? true : undefined}
                onChange={(event) => onChange(event.target.value)}
            />
            {error ? (
                <div className="af-field__error" role="alert">
                    {error}
                </div>
            ) : (
                hint && <span className="af-field__hint">{hint}</span>
            )}
        </div>
    );
}
