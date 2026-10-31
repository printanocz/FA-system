import { formatDate, formatMoney } from '@/lib/format';

export type DashboardPeriod = 'mesic' | 'kvartal' | 'rok';

export type BilledCard = {
    amount: string;
    previous: string;
    /** Změna oproti stejnému období loni v celých %, bez loňských dat null. */
    change: number | null;
    from: string;
    to: string;
};

export type OverdueRow = {
    id: number;
    number: string | null;
    contact: string | null;
    due_date: string;
    overdue_days: number;
    remaining: string;
    currency: string;
};

export type ChartMonth = { month: number; current: string | null; previous: string };

export type VatLimit = {
    year: number;
    turnover: string;
    limit: string;
    immediate_limit: string;
    percent: number;
    remaining: string;
    warning: boolean;
    exceeded: boolean;
    exceeded_immediate: boolean;
};

export type RecentInvoice = {
    id: number;
    number: string | null;
    status: string;
    computed_status: string | null;
    overdue_days: number | null;
    contact: string | null;
    issue_date: string | null;
    due_date: string | null;
    total: string;
    currency: string;
};

export type DashboardProps =
    | { empty: true; period: DashboardPeriod; today: string }
    | {
          empty: false;
          period: DashboardPeriod;
          today: string;
          cards: {
              billed: BilledCard;
              month: BilledCard;
              unpaid: {
                  amount: string;
                  count: number;
                  nearest_due: { date: string; days: number } | null;
              };
              overdue: { amount: string; count: number };
          };
          chart: {
              year: number;
              previous_year: number;
              months: ChartMonth[];
              strongest: { month: number; amount: string } | null;
              total: string;
          };
          vat: VatLimit;
          overdue_list: OverdueRow[];
          recent: RecentInvoice[];
      };

export const MONTHS = [
    'leden',
    'únor',
    'březen',
    'duben',
    'květen',
    'červen',
    'červenec',
    'srpen',
    'září',
    'říjen',
    'listopad',
    'prosinec',
] as const;

/** „v lednu“, „v únoru“ … (6. pád pro nadpisy karet). */
export const MONTHS_IN = [
    'v lednu',
    'v únoru',
    'v březnu',
    'v dubnu',
    'v květnu',
    'v červnu',
    'v červenci',
    'v srpnu',
    'v září',
    'v říjnu',
    'v listopadu',
    'v prosinci',
] as const;

export const MONTHS_SHORT = [
    'led',
    'úno',
    'bře',
    'dub',
    'kvě',
    'čvn',
    'čvc',
    'srp',
    'zář',
    'říj',
    'lis',
    'pro',
] as const;

/** Nadpis karty Fakturováno podle období: „za rok 2026“, „v říjnu“, „ve 4. čtvrtletí“. */
export function billedTitle(period: DashboardPeriod, today: string): string {
    const [year, month] = today.split('-').map(Number);

    switch (period) {
        case 'mesic':
            return `Fakturováno ${MONTHS_IN[month - 1]}`;
        case 'kvartal':
            return `Fakturováno ve ${Math.ceil(month / 3)}. čtvrtletí`;
        default:
            return `Fakturováno za rok ${year}`;
    }
}

/** „+12 %“, „−8 %“ (skutečné minus), „0 %“. */
export function formatChange(change: number): string {
    if (change > 0) return `+${change} %`;
    if (change < 0) return `−${Math.abs(change)} %`;

    return '0 %';
}

/** „dnes“, „zítra“, jinak datum: bez roku letos, s rokem jindy. */
export function dueLabel(days: number, date: string, today: string): string {
    if (days === 0) return 'dnes';
    if (days === 1) return 'zítra';

    return formatDate(date, date.slice(0, 4) !== today.slice(0, 4));
}

/** Částka do osy grafu: „0“, „50 tis.“, „1,2 mil.“. */
export function formatAxis(value: number): string {
    if (value === 0) return '0';

    return new Intl.NumberFormat('cs-CZ', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

/** Jednoduché „pěkné“ maximum osy (1, 2, 2,5, 5 × 10^n), aby mřížka měla kulatá čísla. */
export function niceMax(value: number): number {
    if (value <= 0) return 1;
    const exponent = Math.floor(Math.log10(value));
    const base = 10 ** exponent;
    const fraction = value / base;
    const nice = [1, 2, 2.5, 5, 10].find((step) => fraction <= step) ?? 10;

    return nice * base;
}

export function money(amount: string | number, currency = 'CZK'): string {
    return formatMoney(amount, currency);
}
