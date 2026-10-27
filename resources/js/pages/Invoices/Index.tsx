import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, FileText, ListFilter, Plus, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import ConfirmSheet from '@/components/ConfirmSheet';
import BulkBar from '@/components/invoices/BulkBar';
import FilterSheet from '@/components/invoices/FilterSheet';
import InvoiceMobileList from '@/components/invoices/InvoiceMobileList';
import InvoiceTable, { type RowActions } from '@/components/invoices/InvoiceTable';
import Menu from '@/components/Menu';
import Toolbar from '@/components/Toolbar';
import { useFlash } from '@/hooks/useFlash';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { MOBILE_QUERY } from '@/lib/breakpoints';
import { formatMoney } from '@/lib/format';
import {
    SEGMENTS,
    listParams,
    type InvoiceRow,
    type InvoicesPageProps,
    type ListFilters,
} from '@/lib/invoices';

const SEARCH_DELAY_MS = 250;
const RELOAD = ['invoices', 'pagination', 'filters', 'counts', 'totals'];

export default function InvoicesIndex({
    invoices,
    pagination,
    filters,
    counts,
    totals,
    years,
    contacts,
    hasInvoices,
}: InvoicesPageProps) {
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const flash = useFlash();
    const [query, setQuery] = useState(filters.q);
    const [sentQuery, setSentQuery] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [filterOpen, setFilterOpen] = useState(false);
    const [toDelete, setToDelete] = useState<InvoiceRow | null>(null);
    const [busy, setBusy] = useState(false);
    const currentYear = new Date().getFullYear();

    const visit = (next: Partial<ListFilters>, page = 1) => {
        const merged = { ...filters, ...next };
        setSentQuery(merged.q);
        router.get('/faktury', listParams(merged, page, currentYear), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: RELOAD,
            onSuccess: () => setSelected([]),
        });
    };

    // Hledá se po krátké pauze v psaní, ne při každém znaku.
    useEffect(() => {
        if (query === sentQuery) return;

        const timer = window.setTimeout(() => visit({ q: query }), SEARCH_DELAY_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const filtered =
        filters.stav !== 'vse' ||
        filters.rok !== String(currentYear) ||
        filters.odberatel !== null ||
        filters.q !== '';

    const run = (method: 'post' | 'delete', url: string, data?: Record<string, unknown>) => {
        setBusy(true);
        router[method](url, data as never, {
            preserveScroll: true,
            onSuccess: (page) => {
                flash.handle(page);
                setSelected([]);
            },
            onFinish: () => setBusy(false),
        });
    };

    const actions: RowActions = {
        onPay: (row) => run('post', '/faktury/hromadne-uhrazeni', { ids: [row.id] }),
        onDelete: (row) => setToDelete(row),
        onCopy: (row) => run('post', `/faktury/${row.id}/kopie`),
    };

    const selectedRows = useMemo(
        () => invoices.filter((row) => selected.includes(row.id)),
        [invoices, selected],
    );
    const selectedTotal = selectedRows.reduce((sum, row) => sum + Number(row.total), 0);

    const toggle = (id: number) =>
        setSelected((current) =>
            current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
        );
    const toggleAll = () =>
        setSelected((current) =>
            invoices.every((row) => current.includes(row.id)) ? [] : invoices.map((row) => row.id),
        );

    const yearItems = [
        { label: 'Všechny roky', value: 'vse' },
        ...years.map((year) => ({ label: String(year), value: String(year) })),
    ];
    const selectedContact = contacts.find((contact) => contact.id === filters.odberatel);

    return (
        <>
            <Head title="Faktury vydané" />

            <Toolbar title={isMobile ? 'Faktury' : 'Faktury vydané'}>
                <label className="af-search w-[260px] max-w-full max-tablet:order-last max-tablet:h-10 max-tablet:w-full">
                    <Search strokeWidth={1.75} aria-hidden="true" />
                    <input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Číslo, klient, částka"
                        aria-label="Hledat v fakturách"
                        autoComplete="off"
                    />
                </label>
                <button
                    type="button"
                    className="af-btn af-btn--glass af-btn--icon af-btn--lg tablet:hidden cursor-pointer"
                    aria-label="Filtr"
                    onClick={() => setFilterOpen(true)}
                >
                    <ListFilter strokeWidth={1.75} aria-hidden="true" />
                </button>
                {/* Na mobilu nahrazuje tlačítko kulaté „Nová faktura“ v TabBaru (jedna zelená plocha). */}
                <Link
                    href="/faktury/nova"
                    className="af-btn af-btn--prominent max-tablet:hidden no-underline"
                >
                    <Plus strokeWidth={2} aria-hidden="true" />
                    Nová faktura
                </Link>
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

            {!hasInvoices ? (
                <EmptyState
                    title="Zatím nemáte žádnou fakturu"
                    text="Vytvořte první fakturu, objeví se tady i s přehledem úhrad."
                    action={
                        <Link
                            href="/faktury/nova"
                            className="af-btn af-btn--prominent no-underline"
                        >
                            <Plus strokeWidth={2} aria-hidden="true" />
                            Nová faktura
                        </Link>
                    }
                />
            ) : (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="af-segmented" role="group" aria-label="Stav faktur">
                            {SEGMENTS.filter(
                                (segment) => !isMobile || segment.value !== 'koncepty',
                            ).map((segment) => (
                                <button
                                    key={segment.value}
                                    type="button"
                                    aria-pressed={filters.stav === segment.value}
                                    onClick={() => visit({ stav: segment.value })}
                                >
                                    {segment.label}{' '}
                                    <span className="af-count">{counts[segment.value]}</span>
                                </button>
                            ))}
                        </div>
                        <div className="max-tablet:hidden flex flex-wrap gap-2">
                            <Menu
                                label="Rok"
                                className="af-btn af-btn--sm cursor-pointer"
                                items={yearItems.map((item) => ({
                                    label: item.label,
                                    selected: filters.rok === item.value,
                                    onSelect: () => visit({ rok: item.value }),
                                }))}
                            >
                                {filters.rok === 'vse' ? 'Všechny roky' : `Rok ${filters.rok}`}
                                <ChevronDown
                                    className="size-3.5"
                                    strokeWidth={2}
                                    aria-hidden="true"
                                />
                            </Menu>
                            <Menu
                                label="Odběratel"
                                className="af-btn af-btn--sm cursor-pointer"
                                items={[
                                    {
                                        label: 'Všichni odběratelé',
                                        selected: filters.odberatel === null,
                                        onSelect: () => visit({ odberatel: null }),
                                    },
                                    ...contacts.map((contact) => ({
                                        label: contact.name,
                                        selected: filters.odberatel === contact.id,
                                        onSelect: () => visit({ odberatel: contact.id }),
                                    })),
                                ]}
                            >
                                {selectedContact?.name ?? 'Všichni odběratelé'}
                                <ChevronDown
                                    className="size-3.5"
                                    strokeWidth={2}
                                    aria-hidden="true"
                                />
                            </Menu>
                        </div>
                    </div>

                    <section
                        className="max-tablet:hidden grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3"
                        aria-label="Součty za filtr"
                    >
                        <Total
                            label={`Vystaveno ${totals.count} ${totals.count === 1 ? 'faktura' : totals.count >= 2 && totals.count <= 4 ? 'faktury' : 'faktur'}`}
                            value={totals.total}
                        />
                        <Total label="Uhrazeno" value={totals.paid} />
                        <Total label="Neuhrazeno" value={totals.unpaid} />
                        <Total
                            label="Po splatnosti"
                            value={totals.overdue}
                            alert={Number(totals.overdue) > 0}
                        />
                    </section>

                    {invoices.length === 0 ? (
                        <EmptyState
                            title="Nic jsme nenašli"
                            text="Zkuste jiné hledání nebo zrušte filtry."
                            action={
                                filtered ? (
                                    <button
                                        type="button"
                                        className="af-btn cursor-pointer"
                                        onClick={() => {
                                            setQuery('');
                                            visit({
                                                stav: 'vse',
                                                rok: String(currentYear),
                                                odberatel: null,
                                                q: '',
                                            });
                                        }}
                                    >
                                        Zrušit filtry
                                    </button>
                                ) : undefined
                            }
                        />
                    ) : isMobile ? (
                        <section>
                            <div className="af-list-header flex justify-between">
                                <span>Nejnovější</span>
                                <span className="tabular-nums">
                                    {invoices.length} z {pagination.total}
                                </span>
                            </div>
                            <InvoiceMobileList rows={invoices} />
                            <Pager pagination={pagination} onPage={(page) => visit({}, page)} />
                        </section>
                    ) : (
                        <section className="af-card relative p-2">
                            <InvoiceTable
                                rows={invoices}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                actions={actions}
                            />
                            <Pager pagination={pagination} onPage={(page) => visit({}, page)} />
                        </section>
                    )}

                    {!isMobile && selected.length > 0 && (
                        <BulkBar
                            count={selected.length}
                            total={selectedTotal}
                            busy={busy}
                            onPay={() =>
                                run('post', '/faktury/hromadne-uhrazeni', { ids: selected })
                            }
                            onClear={() => setSelected([])}
                        />
                    )}
                </>
            )}

            <FilterSheet
                open={filterOpen}
                filters={filters}
                counts={counts}
                years={years}
                contacts={contacts}
                onChange={(next) => visit(next)}
                onClose={() => setFilterOpen(false)}
            />

            <ConfirmSheet
                open={toDelete !== null}
                title={toDelete?.number ? `Smazat fakturu ${toDelete.number}?` : 'Smazat koncept?'}
                text={
                    toDelete?.number
                        ? 'Číslo se vrátí do řady, aby v číslování nevznikla díra. Smazání nejde vrátit.'
                        : 'Koncept se smaže natrvalo.'
                }
                confirmLabel="Smazat"
                destructive
                busy={busy}
                onCancel={() => setToDelete(null)}
                onConfirm={() => {
                    if (toDelete) run('delete', `/faktury/${toDelete.id}`);
                    setToDelete(null);
                }}
            />
            {flash.toast}
        </>
    );
}

function Total({ label, value, alert = false }: { label: string; value: string; alert?: boolean }) {
    return (
        <div className="flex flex-col gap-0.5 p-1">
            <span className="text-label-2 text-[13px] leading-[18px]">{label}</span>
            <span
                className={`text-[22px] leading-7 font-semibold tabular-nums ${alert ? 'text-red' : ''}`}
            >
                {formatMoney(value)}
            </span>
        </div>
    );
}

function Pager({
    pagination,
    onPage,
}: {
    pagination: InvoicesPageProps['pagination'];
    onPage: (page: number) => void;
}) {
    if (pagination.total === 0) return null;

    return (
        <div className="text-label-3 flex items-center justify-between px-4 pt-3 pb-1.5 text-[13px]">
            <span>
                Zobrazeno {pagination.from}–{pagination.to} z {pagination.total}
            </span>
            {pagination.last_page > 1 && (
                <div className="flex gap-1.5">
                    <button
                        type="button"
                        className="af-btn af-btn--sm cursor-pointer"
                        disabled={pagination.page <= 1}
                        onClick={() => onPage(pagination.page - 1)}
                    >
                        Předchozí
                    </button>
                    <button
                        type="button"
                        className="af-btn af-btn--sm cursor-pointer"
                        disabled={pagination.page >= pagination.last_page}
                        onClick={() => onPage(pagination.page + 1)}
                    >
                        Další
                    </button>
                </div>
            )}
        </div>
    );
}

function EmptyState({
    title,
    text,
    action,
}: {
    title: string;
    text: string;
    action?: React.ReactNode;
}) {
    return (
        <div className="af-card flex flex-col items-center gap-3 px-6 py-14 text-center">
            <span className="bg-fill text-label-3 grid size-12 place-items-center rounded-full">
                <FileText className="size-6" strokeWidth={1.75} aria-hidden="true" />
            </span>
            <h2 className="m-0 text-[17px] leading-[22px] font-semibold">{title}</h2>
            <p className="text-label-2 m-0 max-w-sm text-[14px] leading-5">{text}</p>
            {action}
        </div>
    );
}
