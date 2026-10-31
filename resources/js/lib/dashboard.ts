import { formatDate, formatMoney, plural } from '@/lib/format';

export type DashboardPeriod = 'mesic' | 'kvartal' | 'rok';

export type BilledCard = {
    amount: string;
    previous: string;
    /** Změna oproti stejnému období loni v celých %, bez loňských dat null. */
    change: number | null;
    from: string;
    to: string;
    previous_year: number;
    /** Faktury v jiné měně než CZK, které se do částky nezapočetly. */
    foreign: ForeignCount[];
};

export type ForeignCount = { currency: string; count: number };

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
    description: string | null;
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
                  foreign: ForeignCount[];
              };
              overdue: { amount: string; count: number; foreign: ForeignCount[] };
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
    'Led',
    'Úno',
    'Bře',
    'Dub',
    'Kvě',
    'Čvn',
    'Čvc',
    'Srp',
    'Zář',
    'Říj',
    'Lis',
    'Pro',
] as const;

/** „lednu“, „únoru“ … (pro „oproti říjnu 2025“). */
export const MONTHS_TO = MONTHS_IN.map((name) => name.replace(/^v(e)? /, ''));

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

/** Popisek první karty na mobilu podle segmentu: „Říjen“, „Q4 2026“, „Rok 2026“. */
export function compactBilledLabel(period: DashboardPeriod, today: string): string {
    const [year, month] = today.split('-').map(Number);
    if (period === 'mesic') return capitalize(MONTHS[month - 1]);
    if (period === 'kvartal') return `Q${Math.ceil(month / 3)} ${year}`;

    return `Rok ${year}`;
}

/** Odkud se bere srovnání: „stejné období 2025“ (desktop), „2025“ / „říjnu 2025“ / „Q4 2025“ (mobil). */
export function previousLabel(period: DashboardPeriod, today: string, compact: boolean): string {
    const [year, month] = today.split('-').map(Number);
    const previous = year - 1;
    if (period === 'mesic') return `${MONTHS_TO[month - 1]} ${previous}`;
    if (!compact) return `stejnému období ${previous}`;
    if (period === 'kvartal') return `Q${Math.ceil(month / 3)} ${previous}`;

    return String(previous);
}

export function capitalize(text: string): string {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

/** „+ 2 faktury v EUR nejsou započteny“ (více měn: „+ 2 faktury v EUR, 1 faktura v USD nejsou započteny“). */
export function foreignNote(foreign: ForeignCount[]): string | null {
    if (foreign.length === 0) return null;
    const total = foreign.reduce((sum, item) => sum + item.count, 0);
    const parts = foreign.map(
        (item) =>
            `${item.count} ${plural(item.count, 'faktura', 'faktury', 'faktur')} v ${item.currency}`,
    );

    return `+ ${parts.join(', ')} ${total === 1 ? 'není započtena' : 'nejsou započteny'}`;
}

/** Částka rozdělená na číslo a měnu pro `af-kpi__value` („610 300“ + <small>Kč</small>). */
export function moneyParts(
    amount: string | number,
    currency = 'CZK',
): { value: string; unit: string } {
    const text = formatMoney(amount, currency);
    const index = text.search(/\s\S+$/);

    return index === -1
        ? { value: text, unit: '' }
        : { value: text.slice(0, index), unit: text.slice(index + 1) };
}

/** Věta pod grafem: nejsilnější měsíc a které měsíce se ještě doplní. */
export function chartInsight(
    strongest: { month: number; amount: string } | null,
    currentMonth: number,
): string {
    const parts: string[] = [];
    parts.push(
        strongest
            ? `Nejsilnější měsíc ${MONTHS[strongest.month - 1]}, ${formatMoney(strongest.amount)}.`
            : 'Letos zatím nemáte žádnou vyfakturovanou částku.',
    );

    const future = MONTHS.slice(currentMonth);
    if (future.length === 1)
        parts.push(`${capitalize(future[0])} se doplní podle vystavených faktur.`);
    if (future.length === 2)
        parts.push(`${capitalize(future[0])} a ${future[1]} se doplní podle vystavených faktur.`);
    if (future.length > 2) {
        parts.push(
            `${capitalize(future[0])} až ${future[future.length - 1]} se doplní podle vystavených faktur.`,
        );
    }

    return parts.join(' ');
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
