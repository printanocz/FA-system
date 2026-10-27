import type { FlashError } from '@/lib/shared';

export type Segment = 'vse' | 'neuhrazene' | 'po-splatnosti' | 'uhrazene' | 'koncepty';

export const SEGMENTS: { value: Segment; label: string }[] = [
    { value: 'vse', label: 'Vše' },
    { value: 'neuhrazene', label: 'Neuhrazené' },
    { value: 'po-splatnosti', label: 'Po splatnosti' },
    { value: 'uhrazene', label: 'Uhrazené' },
    { value: 'koncepty', label: 'Koncepty' },
];

export type InvoiceRow = {
    id: number;
    number: string | null;
    status: string;
    computed_status: string | null;
    overdue_days: number | null;
    contact_id: number | null;
    contact: string | null;
    description: string | null;
    issue_date: string | null;
    due_date: string | null;
    total: string;
    paid_total: string;
    currency: string;
    can_pay: boolean;
    can_delete: boolean;
};

export type ListFilters = {
    stav: Segment;
    /** Rok jako text, nebo `vse` pro všechny roky. */
    rok: string;
    odberatel: number | null;
    q: string;
};

export type InvoiceTotalsSummary = {
    count: number;
    total: string;
    paid: string;
    unpaid: string;
    overdue: string;
};

export type InvoicesPageProps = {
    invoices: InvoiceRow[];
    pagination: {
        page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        per_page: number;
    };
    filters: ListFilters;
    counts: Record<Segment, number>;
    totals: InvoiceTotalsSummary;
    years: number[];
    contacts: { id: number; name: string }[];
    hasInvoices: boolean;
};

/** Query parametry seznamu: výchozí hodnoty (Vše, letošní rok) se do adresy nedávají. */
export function listParams(
    filters: ListFilters,
    page = 1,
    currentYear = new Date().getFullYear(),
): Record<string, string> {
    const params: Record<string, string> = {};
    if (filters.stav !== 'vse') params.stav = filters.stav;
    if (filters.rok !== String(currentYear)) params.rok = filters.rok;
    if (filters.odberatel !== null) params.odberatel = String(filters.odberatel);
    if (filters.q) params.q = filters.q;
    if (page > 1) params.strana = String(page);

    return params;
}

export type PaymentMethodValue = 'bank_transfer' | 'cash';

export const PAYMENT_METHODS: { value: PaymentMethodValue; label: string }[] = [
    { value: 'bank_transfer', label: 'Převodem' },
    { value: 'cash', label: 'Hotově' },
];

export const paymentMethodLabel = (method: string): string =>
    PAYMENT_METHODS.find((item) => item.value === method)?.label ?? method;

export type Party = {
    name?: string | null;
    ico?: string | null;
    dic?: string | null;
    street?: string | null;
    zip?: string | null;
    city?: string | null;
    email?: string | null;
    phone?: string | null;
    web?: string | null;
    registration_note?: string | null;
    vat_payer?: boolean | null;
};

export type BankSnapshot = {
    account_number?: string | null;
    bank_code?: string | null;
    iban?: string | null;
    swift?: string | null;
    name?: string | null;
};

export type InvoiceItemData = {
    id: number;
    name: string;
    description: string | null;
    qty: string;
    unit: string;
    unit_price: string;
    line_total: string;
};

export type PaymentData = {
    id: number;
    amount: string;
    paid_at: string;
    method: string;
    note: string | null;
    deletable: boolean;
};

export type InvoiceDetail = {
    id: number;
    number: string;
    status: string;
    computed_status: string | null;
    overdue_days: number | null;
    issue_date: string;
    due_date: string;
    taxable_date: string | null;
    show_taxable_date: boolean;
    issued_at: string | null;
    sent_at: string | null;
    paid_at: string | null;
    vs: string | null;
    ks: string | null;
    order_number: string | null;
    payment_method: string;
    currency: string;
    subtotal: string;
    discount_percent: string;
    vat_total: string;
    rounding: string;
    total: string;
    paid_total: string;
    remaining: string;
    paid_percent: number;
    text_before: string | null;
    text_after: string | null;
    supplier: Party | null;
    buyer: Party | null;
    bank_account: BankSnapshot | null;
    qr_svg: string | null;
    can_pay: boolean;
    can_delete: boolean;
    items: InvoiceItemData[];
    payments: PaymentData[];
};

export type RelatedDocument = {
    id: number;
    type: string;
    number: string | null;
    status: string;
    url: string | null;
};

export type InvoiceShowProps = {
    invoice: InvoiceDetail;
    contact: {
        id: number;
        name: string;
        ico: string | null;
        stats: { year: number; turnover: string; invoice_count: number };
    } | null;
    related: RelatedDocument[];
};

export type { FlashError };
