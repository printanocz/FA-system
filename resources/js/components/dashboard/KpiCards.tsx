import type { BilledCard, DashboardPeriod, ForeignCount } from '@/lib/dashboard';
import {
    billedTitle,
    compactBilledLabel,
    dueLabel,
    foreignNote,
    formatChange,
    MONTHS,
    MONTHS_IN,
    moneyParts,
    previousLabel,
    capitalize,
} from '@/lib/dashboard';
import { formatDate, plural } from '@/lib/format';

export type KpiData = {
    period: DashboardPeriod;
    today: string;
    billed: BilledCard;
    month: BilledCard;
    unpaid: {
        amount: string;
        count: number;
        nearest_due: { date: string; days: number } | null;
        foreign: ForeignCount[];
    };
    overdue: { amount: string; count: number; foreign: ForeignCount[] };
    /** Jediná faktura po splatnosti (odběratel a dny do karty), když je právě jedna. */
    single: { contact: string | null; days: number } | null;
};

function Value({ amount }: { amount: string }) {
    const { value, unit } = moneyParts(amount);

    return (
        <>
            {value} {unit && <small>{unit}</small>}
        </>
    );
}

function Foreign({ foreign, className }: { foreign: ForeignCount[]; className: string }) {
    const note = foreignNote(foreign);

    return note ? <div className={className}>{note}</div> : null;
}

/** Změna proti loňsku: zelená při růstu, červená při poklesu, bez loňských dat nic. */
function Delta({ change, text }: { change: number | null; text: string }) {
    if (change === null) return null;

    return (
        <div className="af-kpi__delta">
            <b className={change < 0 ? 'is-down' : undefined}>{formatChange(change)}</b> {text}
        </div>
    );
}

/** Čtyři karty Přehledu na desktopu a tabletu (artboard „Přehled — desktop“). */
export function KpiCards({ period, today, billed, month, unpaid, overdue, single }: KpiData) {
    const monthIndex = Number(today.slice(5, 7)) - 1;
    const nearest = unpaid.nearest_due;
    const nearestText = nearest
        ? nearest.days <= 1
            ? `${dueLabel(nearest.days, nearest.date, today)} ${formatDate(nearest.date, false)}`
            : dueLabel(nearest.days, nearest.date, today)
        : null;

    return (
        <section
            aria-label="Souhrn"
            className="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]"
        >
            <div className="af-card af-kpi">
                <h2 className="af-card__title">{billedTitle(period, today)}</h2>
                <div className="af-kpi__value">
                    <Value amount={billed.amount} />
                </div>
                <Delta
                    change={billed.change}
                    text={`oproti ${previousLabel(period, today, false)}`}
                />
                <Foreign foreign={billed.foreign} className="af-kpi__delta" />
            </div>

            <div className="af-card af-kpi">
                <h2 className="af-card__title">Fakturováno {MONTHS_IN[monthIndex]}</h2>
                <div className="af-kpi__value">
                    <Value amount={month.amount} />
                </div>
                <Delta
                    change={month.change}
                    text={`oproti ${previousLabel('mesic', today, false)}`}
                />
                <Foreign foreign={month.foreign} className="af-kpi__delta" />
            </div>

            <div className="af-card af-kpi">
                <h2 className="af-card__title">Neuhrazeno</h2>
                <div className="af-kpi__value">
                    <Value amount={unpaid.amount} />
                </div>
                <div className="af-kpi__delta">
                    {unpaid.count} {plural(unpaid.count, 'faktura', 'faktury', 'faktur')}
                    {nearestText && `, nejbližší splatnost ${nearestText}`}
                </div>
                <Foreign foreign={unpaid.foreign} className="af-kpi__delta" />
            </div>

            <div className="af-card af-kpi">
                <h2 className="af-card__title">Po splatnosti</h2>
                <div className={`af-kpi__value ${overdue.count > 0 ? 'text-red' : ''}`}>
                    <Value amount={overdue.amount} />
                </div>
                <div className="af-kpi__delta">
                    {overdue.count === 0
                        ? 'Nic po splatnosti'
                        : single
                          ? `1 faktura${single.contact ? ` · ${single.contact}` : ''} · ${single.days} ${plural(single.days, 'den', 'dny', 'dní')}`
                          : `${overdue.count} ${plural(overdue.count, 'faktura', 'faktury', 'faktur')}`}
                </div>
                <Foreign foreign={overdue.foreign} className="af-kpi__delta" />
            </div>
        </section>
    );
}

/** Kompaktní mřížka 2 × 2 na mobilu (artboard „Přehled — mobil“). */
export function KpiCardsCompact({ period, today, billed, month, unpaid, overdue }: KpiData) {
    const monthIndex = Number(today.slice(5, 7)) - 1;
    const monthLabel = capitalize(MONTHS[monthIndex]);

    const cards: {
        label: string;
        amount: string;
        red?: boolean;
        change?: number | null;
        text?: string;
        foreign: ForeignCount[];
    }[] = [
        {
            label: compactBilledLabel(period, today),
            amount: billed.amount,
            change: billed.change,
            text: `proti ${previousLabel(period, today, true)}`,
            foreign: billed.foreign,
        },
        {
            label: monthLabel,
            amount: month.amount,
            change: month.change,
            text: `proti ${previousLabel('mesic', today, true)}`,
            foreign: month.foreign,
        },
        {
            label: 'Neuhrazeno',
            amount: unpaid.amount,
            text: `${unpaid.count} ${plural(unpaid.count, 'faktura', 'faktury', 'faktur')}`,
            foreign: unpaid.foreign,
        },
        {
            label: 'Po splatnosti',
            amount: overdue.amount,
            red: overdue.count > 0,
            text: `${overdue.count} ${plural(overdue.count, 'faktura', 'faktury', 'faktur')}`,
            foreign: overdue.foreign,
        },
    ];

    return (
        <section aria-label="Souhrn" className="grid grid-cols-2 gap-3">
            {cards.map((card) => (
                <div key={card.label} className="af-card flex flex-col gap-1 !p-4">
                    <span className="text-label-2 text-[13px] leading-[18px]">{card.label}</span>
                    <span
                        className={`text-[22px] leading-7 font-semibold whitespace-nowrap tabular-nums ${card.red ? 'text-red' : ''}`}
                    >
                        {moneyParts(card.amount).value}
                        {'\u00a0'}
                        {moneyParts(card.amount).unit}
                    </span>
                    {card.change !== undefined && card.change !== null ? (
                        <span
                            className={`text-xs leading-4 font-semibold ${card.change < 0 ? 'text-red' : 'text-accent-text'}`}
                        >
                            {formatChange(card.change)} {card.text}
                        </span>
                    ) : card.change === undefined ? (
                        <span className="text-label-3 text-xs leading-4">{card.text}</span>
                    ) : null}
                    {foreignNote(card.foreign) && (
                        <span className="text-label-3 text-xs leading-4">
                            {foreignNote(card.foreign)}
                        </span>
                    )}
                </div>
            ))}
        </section>
    );
}
