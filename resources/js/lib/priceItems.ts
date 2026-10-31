export type PriceItemRow = {
    id: number;
    name: string;
    note: string;
    code: string;
    unit: string;
    currency: string;
    unit_price: string;
    /** Součet množství na fakturách vystavených v roce, `null` = nepoužito. */
    used_qty: string | null;
    /** Položka je na nějakém dokladu (nejde smazat, jen archivovat). */
    is_used: boolean;
    archived: boolean;
};

export type PriceItemsPageProps = {
    items: PriceItemRow[];
    filters: { q: string; archived: boolean };
    year: number;
};

/** Měrné jednotky z výběru. Vlastní jednotka se píše ručně. */
export const UNITS = ['ks', 'hod.', 'str.', 'm²', 'den', 'měs.', 'kpl'] as const;

export const CURRENCIES = [
    { code: 'CZK', symbol: 'Kč' },
    { code: 'EUR', symbol: '€' },
] as const;

/** „Na fakturách“: u kusů „3×“, jinak množství s jednotkou („142 hod.“), nepoužito „—“. */
export function formatUsage(item: Pick<PriceItemRow, 'used_qty' | 'unit'>): string {
    if (item.used_qty === null) return '—';

    const text = new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 4 }).format(
        Number(item.used_qty),
    );

    return item.unit === 'ks' || item.unit === '' ? `${text}×` : `${text} ${item.unit}`;
}

/** Cena z API („400.00“) do pole s desetinnou čárkou („400,00“). */
export function priceToInput(price: string): string {
    return price.replace('.', ',');
}
