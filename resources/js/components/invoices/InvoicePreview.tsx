import { useLayoutEffect, useRef, useState } from 'react';
import { formatDate, initials } from '@/lib/format';
import { formatDecimal } from '@/lib/invoice';
import { paymentMethodLabel, type InvoiceDetail, type Party } from '@/lib/invoices';

/** Rozměr A4 v px při 96 dpi, ve kterém je papír navržený (artboard „PDF faktury — A4“). */
const PAPER_WIDTH = 794;
const PAPER_HEIGHT = 1123;

const LINE = 'border-[#C9CEC2]';

function addressLines(party: Party | null): string[] {
    if (!party) return [];

    return [party.street, [party.zip, party.city].filter(Boolean).join(' ')].filter(
        (line): line is string => Boolean(line),
    );
}

/**
 * Papír faktury v pevné velikosti A4 podle artboardu „PDF faktury — A4“. Vykresluje ho vždy
 * světle (tištěný doklad nemá tmavý režim). T-10 nahradí tuhle komponentu náhledem skutečného PDF.
 */
export function InvoicePaper({ invoice }: { invoice: InvoiceDetail }) {
    const supplier = invoice.supplier;
    const buyer = invoice.buyer;
    const bank = invoice.bank_account;
    const showBank = invoice.payment_method === 'bank_transfer' && bank !== null;
    const hasDiscount = Number(invoice.discount_percent) > 0;
    const hasRounding = Number(invoice.rounding) !== 0;

    const meta: { label: string; value: string; strong?: boolean; mono?: boolean }[] = [
        { label: 'Datum vystavení', value: formatDate(invoice.issue_date) },
        { label: 'Datum splatnosti', value: formatDate(invoice.due_date), strong: true },
    ];
    if (invoice.show_taxable_date && invoice.taxable_date) {
        meta.push({ label: 'Datum zdan. plnění', value: formatDate(invoice.taxable_date) });
    }
    if (invoice.vs) meta.push({ label: 'Variabilní symbol', value: invoice.vs, mono: true });
    meta.push({ label: 'Způsob úhrady', value: paymentMethodLabel(invoice.payment_method) });
    if (invoice.order_number) meta.push({ label: 'Objednávka č.', value: invoice.order_number });

    return (
        <div
            data-theme="light"
            className="flex flex-col gap-7 text-[13px] leading-[18px]"
            style={{
                backgroundColor: '#FFFFFF',
                color: 'var(--label)',
                colorScheme: 'light',
                width: PAPER_WIDTH,
                height: PAPER_HEIGHT,
                boxSizing: 'border-box',
                padding: '56px 56px 40px',
            }}
        >
            <header className="flex items-start justify-between gap-6">
                <div className="flex flex-col gap-0.5">
                    <span className="text-label-2 text-[14px] leading-[18px]">Faktura č.</span>
                    <span className="text-[40px] leading-[44px] font-bold tracking-[-1px] tabular-nums">
                        {invoice.number}
                    </span>
                </div>
                <div
                    aria-hidden="true"
                    className="bg-accent text-on-accent grid size-14 place-items-center rounded-[14px] text-[20px] font-bold tracking-[-0.5px]"
                >
                    {initials(supplier?.name ?? 'AF')}
                </div>
            </header>

            <section className="grid grid-cols-2 gap-6">
                <div className="flex flex-col gap-1 py-4">
                    <span className="text-label-2 text-xs leading-4">Dodavatel</span>
                    <span className="text-[15px] leading-5 font-bold">{supplier?.name}</span>
                    {addressLines(supplier).map((line) => (
                        <span key={line}>{line}</span>
                    ))}
                    {(supplier?.ico || supplier?.dic) && (
                        <span>
                            {supplier?.ico && (
                                <>
                                    IČ <span className="af-mono">{supplier.ico}</span>
                                </>
                            )}
                            {supplier?.dic && (
                                <>
                                    {supplier?.ico && ' · '}DIČ{' '}
                                    <span className="af-mono">{supplier.dic}</span>
                                </>
                            )}
                        </span>
                    )}
                    {(supplier?.email || supplier?.phone) && (
                        <span className="text-label-2">
                            {[supplier?.email, supplier?.phone].filter(Boolean).join(' · ')}
                        </span>
                    )}
                    {supplier?.web && <span className="text-label-2">{supplier.web}</span>}
                </div>
                <div className="bg-bg flex flex-col gap-1 rounded-[14px] p-4">
                    <span className="text-label-2 text-xs leading-4">Odběratel</span>
                    <span className="text-[15px] leading-5 font-bold">{buyer?.name}</span>
                    {addressLines(buyer).map((line) => (
                        <span key={line}>{line}</span>
                    ))}
                    {(buyer?.ico || buyer?.dic) && (
                        <span>
                            {buyer?.ico && (
                                <>
                                    IČ <span className="af-mono">{buyer.ico}</span>
                                </>
                            )}
                            {buyer?.dic && (
                                <>
                                    {buyer?.ico && ' · '}DIČ{' '}
                                    <span className="af-mono">{buyer.dic}</span>
                                </>
                            )}
                        </span>
                    )}
                </div>
            </section>

            <section
                className={`grid border-y ${LINE}`}
                style={{
                    gridTemplateColumns: `repeat(${Math.min(meta.length, 4)}, minmax(0, 1fr))`,
                }}
            >
                {meta.map((cell, index) => (
                    <div
                        key={cell.label}
                        className={`flex flex-col gap-0.5 py-3 ${index % 4 === 0 ? 'pr-3' : 'px-3'}`}
                    >
                        <span className="text-label-2 text-xs leading-4">{cell.label}</span>
                        <span
                            className={`${cell.mono ? 'af-mono' : ''} ${cell.strong ? 'text-[15px] font-bold' : 'font-medium'}`}
                        >
                            {cell.value}
                        </span>
                    </div>
                ))}
            </section>

            <section className="flex flex-col gap-3.5">
                {invoice.text_before && (
                    <p className="m-0 whitespace-pre-line">{invoice.text_before}</p>
                )}
                <table className="w-full border-collapse text-[13px] leading-[18px]">
                    <thead>
                        <tr className="text-label-2 text-xs leading-4 font-semibold">
                            <th className="border-label border-b-[1.5px] pb-2 text-left font-semibold">
                                Označení dodávky
                            </th>
                            <th className="border-label border-b-[1.5px] pb-2 pl-4 text-right font-semibold">
                                Počet
                            </th>
                            <th className="border-label border-b-[1.5px] pb-2 pl-1 text-left font-semibold">
                                M.j.
                            </th>
                            <th className="border-label border-b-[1.5px] pb-2 pl-4 text-right font-semibold">
                                Cena za m.j.
                            </th>
                            <th className="border-label border-b-[1.5px] pb-2 pl-4 text-right font-semibold">
                                Celkem
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoice.items.map((item) => (
                            <tr key={item.id} className="align-top">
                                <td className={`border-b py-[11px] ${LINE}`}>
                                    {item.name}
                                    {item.description && (
                                        <div className="text-label-2 text-xs leading-4 whitespace-pre-line">
                                            {item.description}
                                        </div>
                                    )}
                                </td>
                                <td
                                    className={`border-b py-[11px] pl-4 text-right whitespace-nowrap tabular-nums ${LINE}`}
                                >
                                    {formatDecimal(item.qty)}
                                </td>
                                <td className={`border-b py-[11px] pl-1 ${LINE}`}>{item.unit}</td>
                                <td
                                    className={`border-b py-[11px] pl-4 text-right whitespace-nowrap tabular-nums ${LINE}`}
                                >
                                    {formatDecimal(item.unit_price)}
                                </td>
                                <td
                                    className={`border-b py-[11px] pl-4 text-right whitespace-nowrap tabular-nums ${LINE}`}
                                >
                                    {formatDecimal(item.line_total)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {invoice.text_after && (
                    <p className="text-label-2 m-0 whitespace-pre-line">{invoice.text_after}</p>
                )}
            </section>

            <div className="grow" />

            <section className="flex items-end justify-between gap-6">
                <div className="flex items-start gap-5">
                    {showBank && invoice.qr_svg && (
                        <figure className="m-0 flex flex-col items-center gap-1.5">
                            <img
                                src={`data:image/svg+xml;utf8,${encodeURIComponent(invoice.qr_svg)}`}
                                alt={`QR Platba pro fakturu ${invoice.number}`}
                                className="block size-[132px]"
                            />
                            <figcaption className="text-xs leading-4 font-semibold">
                                QR Platba
                            </figcaption>
                        </figure>
                    )}
                    {showBank && (
                        <div className="grid grid-cols-[auto_auto] content-start gap-x-4 gap-y-1">
                            <span className="text-label-2 text-xs leading-4">Bankovní účet</span>
                            <span className="af-mono !text-[14px] font-medium">
                                {bank?.account_number}/{bank?.bank_code}
                            </span>
                            {bank?.iban && (
                                <>
                                    <span className="text-label-2 text-xs leading-4">IBAN</span>
                                    <span className="af-mono">{bank.iban}</span>
                                </>
                            )}
                            {bank?.swift && (
                                <>
                                    <span className="text-label-2 text-xs leading-4">SWIFT</span>
                                    <span className="af-mono">{bank.swift}</span>
                                </>
                            )}
                            {invoice.vs && (
                                <>
                                    <span className="text-label-2 text-xs leading-4">
                                        Variabilní symbol
                                    </span>
                                    <span className="af-mono">{invoice.vs}</span>
                                </>
                            )}
                            {invoice.ks && (
                                <>
                                    <span className="text-label-2 text-xs leading-4">
                                        Konstantní symbol
                                    </span>
                                    <span className="af-mono">{invoice.ks}</span>
                                </>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex min-w-60 flex-col items-end gap-2">
                    {(hasDiscount || hasRounding) && (
                        <div className="text-label-2 flex flex-col items-end gap-0.5 text-xs leading-4 tabular-nums">
                            {hasDiscount && (
                                <span>
                                    Sleva {Number(invoice.discount_percent).toLocaleString('cs-CZ')}{' '}
                                    %
                                </span>
                            )}
                            {hasRounding && (
                                <span>Zaokrouhlení {formatDecimal(invoice.rounding)}</span>
                            )}
                        </div>
                    )}
                    <div className="bg-accent text-on-accent flex w-full flex-col items-end gap-1 rounded-[22px] px-6 py-5">
                        <span className="text-[13px] leading-[18px] font-medium">
                            Celkem k úhradě
                        </span>
                        <span className="text-[32px] leading-[38px] font-bold tracking-[-0.5px] whitespace-nowrap tabular-nums">
                            {formatDecimal(invoice.total)} Kč
                        </span>
                    </div>
                </div>
            </section>

            <footer
                className={`text-label-2 flex justify-between gap-4 border-t pt-3 text-xs leading-4 ${LINE}`}
            >
                <span>
                    {[
                        supplier?.registration_note,
                        supplier?.vat_payer ? null : 'Nejsem plátce DPH.',
                    ]
                        .filter(Boolean)
                        .join(' ')}
                </span>
                <span>Strana 1/1</span>
            </footer>
        </div>
    );
}

/**
 * Rámeček náhledu dokladu: papír A4 zmenšený na šířku sloupce (výška se přizpůsobí).
 * Je to jediné místo, které T-10 přepíše na obrázek nebo vložené PDF.
 */
export default function InvoicePreview({ invoice }: { invoice: InvoiceDetail }) {
    const frame = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(0.72);

    useLayoutEffect(() => {
        const node = frame.current;
        if (!node) return;

        const update = () => setScale(Math.min(1, node.clientWidth / PAPER_WIDTH));
        update();

        const observer = new ResizeObserver(update);
        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    return (
        <section aria-label="Náhled PDF" className="w-full min-w-0">
            <div
                ref={frame}
                className="shadow-card overflow-hidden rounded-[14px] bg-white ring-1 ring-black/10 dark:ring-white/15"
                style={{ height: PAPER_HEIGHT * scale }}
            >
                <div
                    style={{
                        width: PAPER_WIDTH,
                        height: PAPER_HEIGHT,
                        transform: `scale(${scale})`,
                        transformOrigin: 'top left',
                    }}
                >
                    <InvoicePaper invoice={invoice} />
                </div>
            </div>
        </section>
    );
}
