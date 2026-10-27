import { formatMoney } from '@/lib/format';

export type ItemRow = {
    /** Klíč řádku jen pro React a fokus, na server se neposílá. */
    key: string;
    name: string;
    description: string;
    qty: string;
    unit: string;
    unit_price: string;
    price_item_id: number | null;
};

/** Hodnoty z editoru. Data a částky jsou text tak, jak je uživatel píše („25. 10. 2026“, „6 800,00“). */
export type InvoiceFormValues = {
    contact_id: number | null;
    issue_date: string;
    due_date: string;
    show_duzp: boolean;
    taxable_date: string;
    order_number: string;
    payment_method: 'bank_transfer' | 'cash';
    bank_account_id: number | null;
    text_before: string;
    text_after: string;
    note_internal: string;
    discount_percent: string;
    round_total: boolean;
    items: ItemRow[];
};

export type ContactSummary = {
    id: number;
    name: string;
    ico: string;
    dic: string;
    street: string;
    zip: string;
    city: string;
    country: string;
    email: string;
    default_due_days: number | null;
    default_discount_percent: string | null;
    ares_synced_at: string | null;
};

export type BankAccountOption = {
    id: number;
    name: string;
    number: string;
    is_default: boolean;
};

export type PriceSuggestion = {
    id: number;
    name: string;
    description: string | null;
    unit: string | null;
    unit_price: string;
    currency: string;
};

export type InvoiceTotals = {
    lines: string[];
    subtotal: string;
    discount_total: string;
    vat_total: string;
    rounding: string;
    total: string;
};

/** Údaje z serveru pro editor (hodnoty formuláře jsou ještě ISO a s tečkou). */
export type ServerForm = {
    contact_id: number | null;
    issue_date: string;
    due_date: string;
    taxable_date: string | null;
    show_taxable_date: boolean;
    order_number: string;
    payment_method: 'bank_transfer' | 'cash';
    bank_account_id: number | null;
    text_before: string;
    text_after: string;
    note_internal: string;
    discount_percent: string;
    round_total: boolean;
    items: {
        name: string;
        description: string;
        qty: string;
        unit: string;
        unit_price: string;
        price_item_id: number | null;
    }[];
};

let counter = 0;
export const newKey = () => `row-${++counter}`;

export const emptyRow = (): ItemRow => ({
    key: newKey(),
    name: '',
    description: '',
    qty: '1',
    unit: 'ks',
    unit_price: '',
    price_item_id: null,
});

/** Je řádek úplně prázdný (nic kromě výchozího počtu a jednotky)? Takový se neukládá. */
export const isBlankRow = (row: ItemRow): boolean =>
    row.name.trim() === '' && row.unit_price.trim() === '' && row.description.trim() === '';

/** Číslo z textu s čárkou a mezerami v tisících („6 800,50“ → 6800.5), jinak `null`. */
export function parseNumber(text: string): number | null {
    const cleaned = text.replace(/[\s\u00A0]/g, '').replace(',', '.');
    if (cleaned === '' || !/^-?\d*\.?\d+$/.test(cleaned)) return null;

    return Number(cleaned);
}

/** Číslo z API („400.00“) do pole s desetinnou čárkou („400,00“). */
export const toInput = (value: string): string => value.replace('.', ',');

/** Částka s haléři a mezerou v tisících, bez měny: „17 900,00“. */
export function formatDecimal(value: string | number): string {
    return new Intl.NumberFormat('cs-CZ', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

export const formatKc = (value: string | number): string => formatMoney(value, 'CZK');

/** Řádek zhruba tak, jak ho spočítá server (jen průběžně, než přijde odpověď). */
export function interimLine(row: ItemRow): number | null {
    const qty = parseNumber(row.qty);
    const price = parseNumber(row.unit_price);
    if (qty === null || price === null) return null;

    return Math.round(qty * price * 100) / 100;
}

/** Datum ISO („2026-10-25“) jako „25. 10. 2026“. */
export function isoToCz(iso: string): string {
    const [year, month, day] = iso.split('-').map(Number);

    return year ? `${day}. ${month}. ${year}` : iso;
}

/** Datum jako „25. 10. 2026“, „25.10.2026“ nebo „25. 10.“ (letošní rok) na ISO, jinak `null`. */
export function czToIso(text: string): string | null {
    const match = /^\s*(\d{1,2})\s*\.\s*(\d{1,2})\s*\.?\s*(\d{4})?\s*$/.exec(text);
    if (!match) return null;

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = match[3] ? Number(match[3]) : new Date().getFullYear();
    const date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return toIso(date);
}

function toIso(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function addDaysIso(iso: string, days: number): string {
    const [year, month, day] = iso.split('-').map(Number);

    return toIso(new Date(year, month - 1, day + days));
}

/** Rozdíl ve dnech `to − from` (celé dny, bez vlivu změny času). */
export function diffDays(fromIso: string, toIsoDate: string): number {
    const parse = (iso: string) => {
        const [year, month, day] = iso.split('-').map(Number);
        return Date.UTC(year, month - 1, day);
    };

    return Math.round((parse(toIsoDate) - parse(fromIso)) / 86_400_000);
}

export function serverToForm(form: ServerForm): InvoiceFormValues {
    return {
        contact_id: form.contact_id,
        issue_date: isoToCz(form.issue_date),
        due_date: isoToCz(form.due_date),
        show_duzp: form.show_taxable_date,
        taxable_date: isoToCz(form.taxable_date ?? form.issue_date),
        order_number: form.order_number,
        payment_method: form.payment_method === 'cash' ? 'cash' : 'bank_transfer',
        bank_account_id: form.bank_account_id,
        text_before: form.text_before,
        text_after: form.text_after,
        note_internal: form.note_internal,
        discount_percent: toInput(String(Number(form.discount_percent))),
        round_total: form.round_total,
        items:
            form.items.length > 0
                ? form.items.map((item) => ({
                      key: newKey(),
                      name: item.name,
                      description: item.description,
                      qty: toInput(item.qty),
                      unit: item.unit,
                      unit_price: formatDecimal(item.unit_price),
                      price_item_id: item.price_item_id,
                  }))
                : [emptyRow()],
    };
}
