export type ContactListItem = {
    id: number;
    name: string;
    ico: string | null;
    turnover: string | null;
    archived: boolean;
};

export type ContactStats = {
    year: number;
    turnover: string;
    invoice_count: number;
    unpaid: string;
    unpaid_next_due: string | null;
    average_payment_days: number | null;
};

export type ContactDocument = {
    id: number;
    type: string;
    number: string | null;
    description: string | null;
    total: string;
    currency: string;
    status: string;
    computed_status: string | null;
    issue_date: string | null;
};

export type ContactCardData = {
    id: number;
    type: 'company' | 'person';
    name: string;
    ico: string;
    dic: string;
    street: string;
    zip: string;
    city: string;
    country: string;
    email: string;
    email_cc: string[];
    phone: string;
    default_due_days: number | null;
    default_discount_percent: string | null;
    note: string;
    ares_synced_at: string | null;
    archived: boolean;
    has_documents: boolean;
    stats: ContactStats;
    documents: ContactDocument[];
};

export type ContactsPageProps = {
    contacts: ContactListItem[];
    filters: { q: string; archived: boolean };
    year: number;
    selectedId: number | null;
    selected: ContactCardData | null;
    defaultDueDays: number;
};

/** Query adresy (`?q=…&archivovane=1`) pro odkazy, které mají zachovat hledání. */
export function filterQuery(filters: { q: string; archived: boolean }): string {
    const params = new URLSearchParams();
    if (filters.q) params.set('q', filters.q);
    if (filters.archived) params.set('archivovane', '1');
    const text = params.toString();

    return text ? `?${text}` : '';
}
