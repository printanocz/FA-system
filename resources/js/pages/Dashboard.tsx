import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import KpiCards from '@/components/dashboard/KpiCards';
import MonthlyChart from '@/components/dashboard/MonthlyChart';
import OverdueList from '@/components/dashboard/OverdueList';
import RecentInvoices from '@/components/dashboard/RecentInvoices';
import VatLimitCard from '@/components/dashboard/VatLimitCard';
import EmailSheet from '@/components/invoices/EmailSheet';
import Toolbar from '@/components/Toolbar';
import { useFlash } from '@/hooks/useFlash';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { MOBILE_QUERY } from '@/lib/breakpoints';
import type { DashboardPeriod, DashboardProps, OverdueRow } from '@/lib/dashboard';
import { MONTHS } from '@/lib/dashboard';
import { formatToday } from '@/lib/date';
import { formatMoney } from '@/lib/format';
import type { EmailDraft } from '@/lib/invoices';

/** Přehled: karty, graf, limit DPH, faktury po splatnosti a poslední doklady. */
export default function Dashboard(props: DashboardProps) {
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const flash = useFlash();
    const year = Number(props.today.slice(0, 4));

    const [reminder, setReminder] = useState<{ id: number; draft: EmailDraft } | null>(null);
    const [reminderOpen, setReminderOpen] = useState(false);
    const [loadingId, setLoadingId] = useState<number | null>(null);

    const periods: { value: DashboardPeriod; label: string }[] = [
        { value: 'mesic', label: 'Měsíc' },
        { value: 'kvartal', label: 'Kvartál' },
        { value: 'rok', label: isMobile ? 'Rok' : `Rok ${year}` },
    ];

    const selectPeriod = (period: DashboardPeriod) => {
        if (period === props.period) return;
        router.get(
            '/',
            { obdobi: period },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    /** Předvyplněný e-mail se načte až při kliknutí, ať Přehled zůstane na jednom lehkém požadavku. */
    const remind = async (row: OverdueRow) => {
        setLoadingId(row.id);
        try {
            const response = await fetch(`/faktury/${row.id}/email-navrh`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(String(response.status));
            setReminder({ id: row.id, draft: (await response.json()) as EmailDraft });
            setReminderOpen(true);
        } catch {
            flash.show('Upomínku se nepodařilo připravit. Zkuste to znovu.');
        } finally {
            setLoadingId(null);
        }
    };

    return (
        <>
            <Head title="Přehled" />
            <Toolbar eyebrow={formatToday(!isMobile)} title="Přehled">
                {!props.empty && (
                    <div
                        className="af-segmented max-tablet:order-last max-tablet:flex max-tablet:basis-full max-tablet:*:flex-1 max-tablet:*:justify-center"
                        role="group"
                        aria-label="Období"
                    >
                        {periods.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                aria-pressed={props.period === item.value}
                                onClick={() => selectPeriod(item.value)}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                )}

                <label className="af-search max-tablet:hidden w-60 max-w-full">
                    <Search strokeWidth={1.75} aria-hidden="true" />
                    <input type="search" placeholder="Hledat doklad nebo klienta" />
                    <kbd>⌘K</kbd>
                </label>
                <button
                    type="button"
                    className="af-btn af-btn--glass af-btn--icon af-btn--lg tablet:hidden cursor-pointer"
                    aria-label="Hledat"
                >
                    <Search strokeWidth={1.75} aria-hidden="true" />
                </button>

                {/* Na mobilu nahrazuje tlačítko kulaté „Nová faktura“ v TabBaru (jedna zelená plocha).
                    V prázdném stavu je jedinou zelenou plochou tlačítko v kartě. */}
                {!props.empty && (
                    <Link
                        className="af-btn af-btn--prominent max-tablet:hidden no-underline"
                        href="/faktury/nova"
                    >
                        <Plus strokeWidth={2} aria-hidden="true" />
                        Nová faktura
                    </Link>
                )}
            </Toolbar>

            {flash.alert && (
                <div
                    role="alert"
                    className="bg-red-soft text-red flex items-start justify-between gap-3 rounded-[14px] px-4 py-3 text-[14px] leading-5"
                >
                    <span>{flash.alert.message}</span>
                    <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 font-semibold text-current"
                        onClick={flash.dismiss}
                    >
                        Zavřít
                    </button>
                </div>
            )}

            {props.empty ? (
                <div className="af-card flex flex-col items-center gap-3 px-6 py-14 text-center">
                    <span className="bg-fill text-label-3 grid size-12 place-items-center rounded-full">
                        <FileText className="size-6" strokeWidth={1.75} aria-hidden="true" />
                    </span>
                    <h2 className="m-0 text-[17px] leading-[22px] font-semibold">
                        Tady uvidíte, jak se vám daří
                    </h2>
                    <p className="text-label-2 m-0 max-w-sm text-[14px] leading-5">
                        Jakmile vystavíte první fakturu, objeví se tu obrat, neuhrazené faktury a
                        sledování limitu pro DPH.
                    </p>
                    <Link href="/faktury/nova" className="af-btn af-btn--prominent no-underline">
                        <Plus strokeWidth={2} aria-hidden="true" />
                        Vystavit první fakturu
                    </Link>
                </div>
            ) : (
                <div className="flex flex-col gap-6">
                    <KpiCards
                        period={props.period}
                        today={props.today}
                        billed={props.cards.billed}
                        month={props.cards.month}
                        unpaid={props.cards.unpaid}
                        overdue={props.cards.overdue}
                        overdueList={props.overdue_list}
                    />

                    <div className="desktop:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] grid grid-cols-1 gap-4">
                        <article className="af-card flex flex-col gap-3">
                            <h2 className="af-card__title">Fakturace po měsících</h2>
                            <MonthlyChart
                                year={props.chart.year}
                                previousYear={props.chart.previous_year}
                                months={props.chart.months}
                                strongest={props.chart.strongest}
                            />
                            <p className="text-label-2 m-0 text-[13px] leading-[18px]">
                                {props.chart.strongest
                                    ? `Nejsilnější měsíc letos je ${MONTHS[props.chart.strongest.month - 1]}: ${formatMoney(props.chart.strongest.amount)}. Celkem letos ${formatMoney(props.chart.total)}.`
                                    : 'Letos zatím nemáte žádnou vyfakturovanou částku.'}
                            </p>
                        </article>
                        <VatLimitCard vat={props.vat} />
                    </div>

                    <div className="desktop:grid-cols-2 grid grid-cols-1 items-start gap-6">
                        <OverdueList
                            rows={props.overdue_list}
                            total={props.cards.overdue.count}
                            loadingId={loadingId}
                            onRemind={remind}
                        />
                        <RecentInvoices rows={props.recent} />
                    </div>
                </div>
            )}

            {flash.toast}

            {reminder && (
                <EmailSheet
                    open={reminderOpen}
                    kind="reminder"
                    invoiceId={reminder.id}
                    draft={reminder.draft}
                    onClose={() => setReminderOpen(false)}
                    onDone={flash.handle}
                />
            )}
        </>
    );
}
