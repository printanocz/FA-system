import { Search } from 'lucide-react';
import { useState } from 'react';
import Sheet from '@/components/Sheet';
import { useSuggest } from '@/hooks/useSuggest';
import { formatMoney } from '@/lib/format';
import type { PriceSuggestion } from '@/lib/invoice';

type PriceListSheetProps = {
    open: boolean;
    onClose: () => void;
    onPick: (item: PriceSuggestion) => void;
};

/** Sheet „Vložit z ceníku“: hledání a seznam položek podle použití. */
export default function PriceListSheet({ open, onClose, onPick }: PriceListSheetProps) {
    return (
        <Sheet open={open} onClose={onClose} side="bottom" label="Vložit z ceníku">
            {open && <PriceListBody onClose={onClose} onPick={onPick} />}
        </Sheet>
    );
}

function PriceListBody({ onClose, onPick }: Omit<PriceListSheetProps, 'open'>) {
    const [query, setQuery] = useState('');
    const items = useSuggest<PriceSuggestion>('/cenik/hledat', 'items', query, true);

    return (
        <div className="af-sheet min-h-0 overflow-y-auto overscroll-contain">
            <div className="af-sheet__grabber" aria-hidden="true" />
            <h2 className="af-sheet__title">Vložit z ceníku</h2>
            <label className="af-search h-10">
                <Search strokeWidth={1.75} aria-hidden="true" />
                <input
                    type="search"
                    value={query}
                    autoFocus
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Název nebo kód"
                    aria-label="Hledat v ceníku"
                    autoComplete="off"
                />
            </label>

            <ul className="m-0 flex list-none flex-col p-0">
                {items.map((item) => (
                    <li key={item.id}>
                        <button type="button" className="pi-row" onClick={() => onPick(item)}>
                            <span className="flex min-w-0 flex-1 flex-col text-left">
                                <span className="truncate font-semibold">{item.name}</span>
                                {item.description && (
                                    <span className="text-label-3 truncate text-xs">
                                        {item.description}
                                    </span>
                                )}
                            </span>
                            <span className="text-[14px] whitespace-nowrap tabular-nums">
                                {formatMoney(item.unit_price, item.currency)}
                                {item.unit && <span className="text-label-3"> / {item.unit}</span>}
                            </span>
                        </button>
                    </li>
                ))}
                {items.length === 0 && (
                    <li className="text-label-3 py-6 text-center text-[14px]">
                        {query ? 'Nic nenalezeno.' : 'Ceník je zatím prázdný.'}
                    </li>
                )}
            </ul>

            <div className="af-sheet__actions">
                <button type="button" className="af-btn" onClick={onClose}>
                    Zavřít
                </button>
            </div>
        </div>
    );
}
