import type { BilledCard, DashboardPeriod, OverdueRow } from '@/lib/dashboard';
import { billedTitle, dueLabel, formatChange, MONTHS_IN } from '@/lib/dashboard';
import { formatMoney, plural } from '@/lib/format';

type Unpaid = {
    amount: string;
    count: number;
    nearest_due: { date: string; days: number } | null;
};

type KpiCardsProps = {
    period: DashboardPeriod;
    today: string;
    billed: BilledCard;
    month: BilledCard;
    unpaid: Unpaid;
    overdue: { amount: string; count: number };
    overdueList: OverdueRow[];
};

/** Procento změny: zelené při růstu, červené při poklesu; bez loňských dat se nezobrazí. */
function Change({ change, label }: { change: number | null; label: string }) {
    if (change === null) return null;

    return (
        <span className="af-kpi__delta">
            <b className={change < 0 ? 'is-down' : undefined}>{formatChange(change)}</b>
            {label}
        </span>
    );
}

/** Čtyři karty Přehledu: fakturováno za období, v tomto měsíci, neuhrazeno a po splatnosti. */
export default function KpiCards({
    period,
    today,
    billed,
    month,
    unpaid,
    overdue,
    overdueList,
}: KpiCardsProps) {
    const monthIndex = Number(today.slice(5, 7)) - 1;
    const single = overdue.count === 1 ? overdueList[0] : undefined;

    return (
        <section
            aria-label="Souhrn"
            className="max-tablet:grid-cols-1 grid grid-cols-2 gap-4 desktop:grid-cols-4"
        >
            <article className="af-card af-kpi">
                <h2 className="af-card__title">{billedTitle(period, today)}</h2>
                <div className="af-kpi__value">{formatMoney(billed.amount)}</div>
                <Change change={billed.change} label="oproti stejnému období loni" />
            </article>

            <article className="af-card af-kpi">
                <h2 className="af-card__title">Fakturováno {MONTHS_IN[monthIndex]}</h2>
                <div className="af-kpi__value">{formatMoney(month.amount)}</div>
                <Change change={month.change} label="oproti stejnému měsíci loni" />
            </article>

            <article className="af-card af-kpi">
                <h2 className="af-card__title">Neuhrazeno</h2>
                <div className="af-kpi__value">{formatMoney(unpaid.amount)}</div>
                <span className="af-kpi__delta">
                    {unpaid.count} {plural(unpaid.count, 'faktura', 'faktury', 'faktur')}
                    {unpaid.nearest_due && (
                        <>
                            {' · '}nejbližší splatnost{' '}
                            {dueLabel(unpaid.nearest_due.days, unpaid.nearest_due.date, today)}
                        </>
                    )}
                </span>
            </article>

            <article className="af-card af-kpi">
                <h2 className="af-card__title">Po splatnosti</h2>
                <div
                    className={`af-kpi__value ${overdue.count > 0 ? 'text-red' : ''}`}
                    data-testid="overdue-amount"
                >
                    {formatMoney(overdue.amount)}
                </div>
                <span className="af-kpi__delta">
                    {overdue.count === 0 ? (
                        'Nic po splatnosti'
                    ) : single ? (
                        <>
                            {single.contact ?? single.number} · {single.overdue_days}{' '}
                            {plural(single.overdue_days, 'den', 'dny', 'dní')}
                        </>
                    ) : (
                        <>
                            {overdue.count} {plural(overdue.count, 'faktura', 'faktury', 'faktur')}
                        </>
                    )}
                </span>
            </article>
        </section>
    );
}
