import { Head, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PriceItemForm from '@/components/price-items/PriceItemForm';
import Sheet from '@/components/Sheet';
import { Toggle } from '@/components/settings/SettingsRow';
import { useToast } from '@/components/Toast';
import Toolbar from '@/components/Toolbar';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { MOBILE_QUERY } from '@/lib/breakpoints';
import { formatPrice } from '@/lib/format';
import { formatUsage, type PriceItemsPageProps } from '@/lib/priceItems';

const SEARCH_DELAY_MS = 250;

/** `undefined` = výchozí (desktop: první položka), `null` = zavřeno, číslo = položka, `'new'` = nová. */
type Mode = undefined | null | number | 'new';

export default function PriceItemsIndex({ items, filters, year }: PriceItemsPageProps) {
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const { show, toast } = useToast();
    const [query, setQuery] = useState(filters.q);
    const [mode, setMode] = useState<Mode>(undefined);
    const lastSent = useRef(filters.q);

    const visit = (next: { q: string; archived: boolean }) => {
        const params: Record<string, string> = {};
        if (next.q) params.q = next.q;
        if (next.archived) params.archivovane = '1';

        router.get('/cenik', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['items', 'filters'],
        });
    };

    // Hledá se po krátké pauze v psaní, ne při každém znaku.
    useEffect(() => {
        if (query === lastSent.current) return;

        const timer = window.setTimeout(() => {
            lastSent.current = query;
            visit({ q: query, archived: filters.archived });
        }, SEARCH_DELAY_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const current: Mode = mode === undefined ? (isMobile ? null : (items[0]?.id ?? null)) : mode;
    const item =
        typeof current === 'number' ? (items.find((row) => row.id === current) ?? null) : null;
    const editing = current === 'new' || item !== null;
    const close = () => setMode(null);

    return (
        <>
            <Head title="Ceník" />

            <Toolbar title="Ceník">
                <label className="af-search w-[260px] max-w-full max-tablet:order-last max-tablet:w-full">
                    <Search strokeWidth={1.75} aria-hidden="true" />
                    <input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Název nebo kód"
                        aria-label="Hledat v ceníku"
                        autoComplete="off"
                    />
                </label>
                {isMobile ? (
                    <button
                        type="button"
                        className="af-btn af-btn--glass af-btn--icon af-btn--lg"
                        aria-label="Nová položka"
                        onClick={() => setMode('new')}
                    >
                        <Plus strokeWidth={2} aria-hidden="true" />
                    </button>
                ) : (
                    <button
                        type="button"
                        className="af-btn af-btn--prominent"
                        onClick={() => setMode('new')}
                    >
                        <Plus strokeWidth={2} aria-hidden="true" />
                        Nová položka
                    </button>
                )}
            </Toolbar>

            <div className="flex flex-wrap items-start gap-5">
                <section
                    className="af-card min-w-0 flex-[999_1_560px] p-2 max-tablet:basis-full"
                    aria-label="Položky ceníku"
                >
                    {items.length === 0 ? (
                        <p className="text-label-3 m-0 px-3 py-6 text-center text-[14px]">
                            {filters.q ? 'Nic nenalezeno.' : 'Ceník je zatím prázdný.'}
                        </p>
                    ) : isMobile ? (
                        <ul className="m-0 flex list-none flex-col p-0">
                            {items.map((row) => (
                                <li key={row.id}>
                                    <button
                                        type="button"
                                        className="pi-row"
                                        onClick={() => setMode(row.id)}
                                    >
                                        <span className="flex min-w-0 flex-1 flex-col text-left">
                                            <span
                                                className={`overflow-hidden font-semibold text-ellipsis whitespace-nowrap ${row.archived ? 'text-label-3' : ''}`}
                                            >
                                                {row.name}
                                            </span>
                                            <span className="text-label-3 overflow-hidden text-xs leading-4 text-ellipsis whitespace-nowrap">
                                                {row.archived
                                                    ? 'Archivováno'
                                                    : [row.code, row.note]
                                                          .filter(Boolean)
                                                          .join(' · ')}
                                            </span>
                                        </span>
                                        <span className="flex flex-col items-end">
                                            <span className="text-[14px] whitespace-nowrap tabular-nums">
                                                {formatPrice(row.unit_price, row.currency)}
                                                {row.unit && (
                                                    <span className="text-label-3">
                                                        {' '}
                                                        / {row.unit}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="text-label-3 text-xs">
                                                {formatUsage(row)} v {year}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="af-table-wrap">
                            <table className="af-table">
                                <thead>
                                    <tr>
                                        <th>Položka</th>
                                        <th>Kód</th>
                                        <th>MJ</th>
                                        <th className="is-num">Cena za MJ</th>
                                        <th className="is-num">Na fakturách {year}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((row) => (
                                        <tr
                                            key={row.id}
                                            aria-selected={item?.id === row.id}
                                            onClick={() => setMode(row.id)}
                                            className="cursor-pointer"
                                        >
                                            <td>
                                                <button
                                                    type="button"
                                                    className="pi-name"
                                                    onClick={() => setMode(row.id)}
                                                >
                                                    <span
                                                        className={`af-cell-main ${row.archived ? 'text-label-3' : ''}`}
                                                    >
                                                        {row.name}
                                                    </span>
                                                    <span className="af-cell-sub block">
                                                        {row.archived ? 'Archivováno' : row.note}
                                                    </span>
                                                </button>
                                            </td>
                                            <td>
                                                <span className="af-mono">{row.code}</span>
                                            </td>
                                            <td>{row.unit}</td>
                                            <td className="is-num">
                                                {formatPrice(row.unit_price, row.currency)}
                                            </td>
                                            <td className="is-num text-label-2">
                                                {formatUsage(row)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="flex items-center justify-between gap-3 px-3 pt-3 pb-2 text-[14px]">
                        <span>Zobrazit archivované</span>
                        <Toggle
                            checked={filters.archived}
                            label="Zobrazit archivované"
                            onChange={(archived) => visit({ q: filters.q, archived })}
                        />
                    </div>
                    <p className="text-label-3 m-0 px-3 pt-1 pb-2 text-xs leading-4">
                        Položky se nabízejí v editoru faktury při psaní popisu. Změna ceny se
                        nepromítne do už vystavených dokladů.
                    </p>
                </section>

                {!isMobile && editing && (
                    <aside
                        className="af-card flex min-w-0 flex-[1_1_320px] flex-col gap-4"
                        aria-label="Úpravy položky"
                    >
                        <PriceItemForm
                            key={item?.id ?? 'new'}
                            item={item}
                            variant="panel"
                            onClose={close}
                            onToast={show}
                        />
                    </aside>
                )}
            </div>

            {isMobile && (
                <Sheet open={editing} onClose={close} side="bottom" label="Položka ceníku">
                    {editing && (
                        <div className="af-sheet min-h-0 overflow-y-auto overscroll-contain">
                            <PriceItemForm
                                key={item?.id ?? 'new'}
                                item={item}
                                variant="sheet"
                                onClose={close}
                                onToast={show}
                            />
                        </div>
                    )}
                </Sheet>
            )}
            {toast}
        </>
    );
}
