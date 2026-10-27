import { useHttp } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    interimLine,
    parseNumber,
    type InvoiceFormValues,
    type InvoiceTotals,
    type ItemRow,
} from '@/lib/invoice';

const DELAY_MS = 300;

type Payload = {
    items: { qty: string; unit_price: string }[];
    discount_percent: string;
    round_total: boolean;
};

export type LiveTotals = InvoiceTotals & {
    /** Součty už potvrdil server (DocumentCalculator), ne jen průběžný odhad. */
    confirmed: boolean;
    /** Částka řádku podle klíče řádku. */
    lineByKey: Record<string, string>;
    count: number;
};

const money = (value: number) => (Math.round(value * 100) / 100).toFixed(2);

/** Průběžný odhad, než přijde odpověď serveru: součet řádků, sleva a zaokrouhlení. */
function interim(rows: ItemRow[], discount: number, round: boolean): InvoiceTotals {
    const lines = rows.map((row) => interimLine(row) ?? 0);
    const subtotal = lines.reduce((sum, line) => sum + line, 0);
    const discountTotal = Math.round(subtotal * discount) / 100;
    const net = subtotal - discountTotal;
    const rounding = round ? Math.round(net) - net : 0;

    return {
        lines: lines.map(money),
        subtotal: money(subtotal),
        discount_total: money(discountTotal),
        vat_total: '0.00',
        rounding: money(rounding),
        total: money(net + rounding),
    };
}

/**
 * Součty editoru. Hned po změně se ukáže průběžný odhad, po 300 ms se zeptá serveru
 * (DocumentCalculator je jediný zdroj pravdy) a odpověď odhad nahradí.
 */
export function useInvoiceTotals(
    values: Pick<InvoiceFormValues, 'items' | 'discount_percent' | 'round_total'>,
): LiveTotals {
    const http = useHttp<Payload, InvoiceTotals>('post', '/faktury/prepocet', {
        items: [],
        discount_percent: '0',
        round_total: false,
    });
    const [server, setServer] = useState<{ key: string; totals: InvoiceTotals } | null>(null);
    const latest = useRef(0);

    const rows = useMemo(
        () =>
            values.items.filter(
                (row) => parseNumber(row.qty) !== null && parseNumber(row.unit_price) !== null,
            ),
        [values.items],
    );
    const discount = parseNumber(values.discount_percent);
    const validDiscount = discount !== null && discount >= 0 && discount <= 100;

    const key = JSON.stringify([
        rows.map((row) => [row.qty, row.unit_price]),
        values.discount_percent,
        values.round_total,
    ]);

    useEffect(() => {
        if (!validDiscount) return;

        const id = ++latest.current;
        const timer = window.setTimeout(() => {
            http.transform(() => ({
                items: rows.map((row) => ({ qty: row.qty, unit_price: row.unit_price })),
                discount_percent: values.discount_percent,
                round_total: values.round_total,
            }));
            http.post('/faktury/prepocet')
                .then((totals) => {
                    if (id === latest.current) setServer({ key, totals });
                })
                .catch(() => undefined);
        }, DELAY_MS);

        return () => window.clearTimeout(timer);
        // `key` nese všechno, co se do výpočtu promítá.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key, validDiscount]);

    const confirmed = server !== null && server.key === key;
    const totals = confirmed
        ? server.totals
        : interim(rows, validDiscount ? (discount ?? 0) : 0, values.round_total);

    const lineByKey: Record<string, string> = {};
    rows.forEach((row, index) => {
        lineByKey[row.key] = totals.lines[index] ?? '0.00';
    });

    return {
        ...totals,
        confirmed,
        lineByKey,
        count: values.items.filter((r) => r.name.trim() !== '').length,
    };
}
