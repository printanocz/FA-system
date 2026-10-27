import { Plus, Tag, X } from 'lucide-react';
import {
    useEffect,
    useId,
    useLayoutEffect,
    useRef,
    useState,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
import PriceListSheet from '@/components/invoice/PriceListSheet';
import { useSuggest } from '@/hooks/useSuggest';
import { formatMoney } from '@/lib/format';
import {
    emptyRow,
    formatDecimal,
    isBlankRow,
    newKey,
    toInput,
    type ItemRow,
    type PriceSuggestion,
} from '@/lib/invoice';

type Column = 'name' | 'qty' | 'unit' | 'unit_price';

type ItemsSectionProps = {
    items: ItemRow[];
    onChange: (items: ItemRow[]) => void;
    /** Chyby ze serveru, klíče `items.N.pole` (N = pořadí mezi neprázdnými řádky). */
    errors: Record<string, string | undefined>;
    lineByKey: Record<string, string>;
    textBefore: string;
    isMobile: boolean;
};

const focusCell = (key: string, column: Column) =>
    document.querySelector<HTMLInputElement>(`[data-cell="${key}:${column}"]`)?.focus();

const rowFromSuggestion = (row: ItemRow, item: PriceSuggestion): ItemRow => ({
    ...row,
    name: item.name,
    description: item.description ?? '',
    unit: item.unit ?? '',
    unit_price: toInput(item.unit_price),
    qty: row.qty.trim() === '' ? '1' : row.qty,
    price_item_id: item.id,
});

/** Karta „Položky“: editovatelná tabulka (mobil: karty), našeptávač z ceníku a vložení z ceníku. */
export default function ItemsSection({
    items,
    onChange,
    errors,
    lineByKey,
    textBefore,
    isMobile,
}: ItemsSectionProps) {
    // Řádek, který se teprve vykreslí (po „Přidat položku“), dostane fokus po dalším vykreslení.
    const pendingFocus = useRef<{ key: string; column: Column } | null>(null);
    const [pickerOpen, setPickerOpen] = useState(false);

    // Chyby se hlásí podle pořadí mezi neprázdnými řádky, tak jak se řádky odesílají.
    const submittedIndex = new Map<string, number>();
    items
        .filter((row) => !isBlankRow(row))
        .forEach((row, index) => submittedIndex.set(row.key, index));

    const rowErrors = (row: ItemRow) => {
        const index = submittedIndex.get(row.key);
        const pick = (field: string) =>
            index === undefined ? undefined : errors[`items.${index}.${field}`];

        return {
            name: pick('name'),
            qty: pick('qty'),
            unit: pick('unit'),
            unit_price: pick('unit_price'),
        };
    };

    useEffect(() => {
        const request = pendingFocus.current;
        if (!request) return;

        pendingFocus.current = null;
        focusCell(request.key, request.column);
    });

    const update = (key: string, patch: Partial<ItemRow>) =>
        onChange(items.map((row) => (row.key === key ? { ...row, ...patch } : row)));

    const remove = (key: string) => {
        const rest = items.filter((row) => row.key !== key);
        onChange(rest.length > 0 ? rest : [emptyRow()]);
    };

    const add = (row: ItemRow = emptyRow(), focus: Column = 'name') => {
        // Prázdný poslední řádek se při vložení z ceníku nahradí, ať nezůstane navíc.
        const base =
            items.length > 0 && isBlankRow(items[items.length - 1]) && row.name !== ''
                ? items.slice(0, -1)
                : items;
        onChange([...base, row]);
        pendingFocus.current = { key: row.key, column: focus };
    };

    const onEnter = (event: KeyboardEvent, row: ItemRow, column: Column) => {
        if (event.key !== 'Enter' || event.nativeEvent.isComposing) return;
        event.preventDefault();

        const index = items.findIndex((candidate) => candidate.key === row.key);
        const next = items[index + 1];
        if (next) {
            focusCell(next.key, column);
        } else {
            add();
        }
    };

    const rows = items.map((row) => (
        <ItemRowEditor
            key={row.key}
            row={row}
            errors={rowErrors(row)}
            total={lineByKey[row.key]}
            isMobile={isMobile}
            onChange={(patch) => update(row.key, patch)}
            onPick={(item) => {
                update(row.key, rowFromSuggestion(row, item));
                pendingFocus.current = { key: row.key, column: 'qty' };
            }}
            onRemove={() => remove(row.key)}
            onEnter={(event, column) => onEnter(event, row, column)}
        />
    ));

    return (
        <section className="af-card flex flex-col gap-1 p-2 max-tablet:p-3" aria-labelledby="h-pol">
            <div className="flex flex-wrap items-center justify-between gap-2 px-4 pt-4 pb-2">
                <h2 id="h-pol" className="m-0 text-[20px] leading-[25px] font-semibold">
                    Položky
                </h2>
                {textBefore.trim() !== '' && (
                    <span className="text-label-3 max-w-[60%] truncate text-[13px]">
                        {textBefore.split('\n')[0]}
                    </span>
                )}
            </div>

            {isMobile ? (
                <div className="flex flex-col gap-3 px-1">{rows}</div>
            ) : (
                <div className="af-table-wrap">
                    <table className="af-table min-w-[640px]">
                        <thead>
                            <tr>
                                <th>Popis</th>
                                <th className="is-num w-20">Počet</th>
                                <th className="w-[72px]">MJ</th>
                                <th className="is-num w-[120px]">Cena za MJ</th>
                                <th className="is-num w-[120px]">Celkem</th>
                                <th className="w-9">
                                    <span className="sr-only">Odebrat</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>{rows}</tbody>
                    </table>
                </div>
            )}

            <div className="flex flex-wrap items-center gap-2 px-3 pt-2 pb-3">
                <button type="button" className="af-btn af-btn--plain" onClick={() => add()}>
                    <Plus strokeWidth={2} aria-hidden="true" />
                    Přidat položku
                </button>
                <button type="button" className="af-btn" onClick={() => setPickerOpen(true)}>
                    <Tag strokeWidth={1.75} aria-hidden="true" />
                    Vložit z ceníku
                </button>
                <span className="text-label-3 ml-auto text-xs leading-4 max-tablet:hidden">
                    Tab přeskakuje mezi buňkami, Enter přidá další řádek.
                </span>
            </div>

            <PriceListSheet
                open={pickerOpen}
                onClose={() => setPickerOpen(false)}
                onPick={(item) => {
                    setPickerOpen(false);
                    add(rowFromSuggestion({ ...emptyRow(), key: newKey() }, item), 'qty');
                }}
            />
        </section>
    );
}

type RowEditorProps = {
    row: ItemRow;
    errors: Record<Column, string | undefined>;
    total: string | undefined;
    isMobile: boolean;
    onChange: (patch: Partial<ItemRow>) => void;
    onPick: (item: PriceSuggestion) => void;
    onRemove: () => void;
    onEnter: (event: KeyboardEvent, column: Column) => void;
};

function ItemRowEditor({
    row,
    errors,
    total,
    isMobile,
    onChange,
    onPick,
    onRemove,
    onEnter,
}: RowEditorProps) {
    const listId = useId();
    const nameRef = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const [rect, setRect] = useState<{ top: number; left: number; width: number } | null>(null);

    // Z ceníku se nabízí jen u řádku, který ještě není vázaný na položku ceníku.
    const suggestions = useSuggest<PriceSuggestion>(
        '/cenik/hledat',
        'items',
        row.name,
        open && row.price_item_id === null,
    );
    const showList = open && row.price_item_id === null && suggestions.length > 0;

    useLayoutEffect(() => {
        if (!showList) return;

        const measure = () => {
            const box = nameRef.current?.getBoundingClientRect();
            if (box)
                setRect({ top: box.bottom + 4, left: box.left, width: Math.max(box.width, 320) });
        };
        measure();
        window.addEventListener('scroll', measure, true);
        window.addEventListener('resize', measure);

        return () => {
            window.removeEventListener('scroll', measure, true);
            window.removeEventListener('resize', measure);
        };
    }, [showList, suggestions.length]);

    const pick = (index: number) => {
        const item = suggestions[index];
        if (!item) return;
        setOpen(false);
        onPick(item);
    };

    const onNameKeyDown = (event: KeyboardEvent) => {
        if (showList) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive((value) => (value + 1) % suggestions.length);
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive((value) => (value - 1 + suggestions.length) % suggestions.length);
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                pick(Math.min(active, suggestions.length - 1));
                return;
            }
            if (event.key === 'Escape') {
                setOpen(false);
                return;
            }
        }
        onEnter(event, 'name');
    };

    const error = errors.name ?? errors.qty ?? errors.unit ?? errors.unit_price;

    const nameInput = (
        <>
            <input
                ref={nameRef}
                className="ed-cell"
                data-cell={`${row.key}:name`}
                value={row.name}
                placeholder="Popis položky"
                aria-label="Popis položky"
                aria-invalid={errors.name ? true : undefined}
                role="combobox"
                aria-expanded={showList}
                aria-controls={listId}
                aria-autocomplete="list"
                autoComplete="off"
                onFocus={() => setOpen(true)}
                onBlur={() => window.setTimeout(() => setOpen(false), 120)}
                onChange={(event) => {
                    setActive(0);
                    setOpen(true);
                    // Ruční úprava popisu přeruší vazbu na položku ceníku.
                    onChange({ name: event.target.value, price_item_id: null });
                }}
                onKeyDown={onNameKeyDown}
            />
            {showList && rect && (
                <div
                    id={listId}
                    role="listbox"
                    aria-label="Položky ceníku"
                    className="af-suggest fixed z-40"
                    style={{ top: rect.top, left: rect.left, width: rect.width }}
                >
                    {suggestions.map((item, index) => (
                        <div
                            key={item.id}
                            role="option"
                            aria-selected={active === index}
                            className="af-suggest__item cursor-pointer"
                            onMouseEnter={() => setActive(index)}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                pick(index);
                            }}
                        >
                            <span className="flex min-w-0 flex-col">
                                <span className="truncate">{item.name}</span>
                                {item.description && (
                                    <span className="text-label-3 truncate text-xs">
                                        {item.description}
                                    </span>
                                )}
                            </span>
                            <span className="af-suggest__meta whitespace-nowrap">
                                {formatMoney(item.unit_price, item.currency)}
                                {item.unit ? ` / ${item.unit}` : ''}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </>
    );

    const qtyInput = (
        <input
            className="ed-cell text-right tabular-nums"
            data-cell={`${row.key}:qty`}
            value={row.qty}
            inputMode="decimal"
            aria-label="Počet"
            aria-invalid={errors.qty ? true : undefined}
            onChange={(event) => onChange({ qty: event.target.value })}
            onKeyDown={(event) => onEnter(event, 'qty')}
        />
    );
    const unitInput = (
        <input
            className="ed-cell"
            data-cell={`${row.key}:unit`}
            value={row.unit}
            aria-label="Měrná jednotka"
            aria-invalid={errors.unit ? true : undefined}
            maxLength={20}
            onChange={(event) => onChange({ unit: event.target.value })}
            onKeyDown={(event) => onEnter(event, 'unit')}
        />
    );
    const priceInput = (
        <input
            className="ed-cell text-right tabular-nums"
            data-cell={`${row.key}:unit_price`}
            value={row.unit_price}
            inputMode="decimal"
            placeholder="0,00"
            aria-label="Cena za jednotku"
            aria-invalid={errors.unit_price ? true : undefined}
            onChange={(event) => onChange({ unit_price: event.target.value })}
            onKeyDown={(event) => onEnter(event, 'unit_price')}
        />
    );
    const totalText = total === undefined ? '—' : formatDecimal(total);

    const errorText: ReactNode = error && (
        <div className="af-field__error px-2.5 pb-1" role="alert">
            {error}
        </div>
    );
    const description = row.description && (
        <div className="text-label-3 truncate px-2.5 pb-1 text-xs leading-4">{row.description}</div>
    );

    if (isMobile) {
        return (
            <div className="bg-fill flex flex-col gap-1 rounded-[14px] p-2">
                <div className="flex items-center gap-1">
                    <div className="min-w-0 flex-1">{nameInput}</div>
                    <button
                        type="button"
                        className="af-btn af-btn--icon af-btn--sm"
                        aria-label="Odebrat položku"
                        onClick={onRemove}
                    >
                        <X strokeWidth={2} aria-hidden="true" />
                    </button>
                </div>
                {description}
                <div className="grid grid-cols-[1fr_1fr] gap-x-2 gap-y-1">
                    <label className="flex flex-col text-xs">
                        <span className="text-label-3 px-2.5">Počet</span>
                        {qtyInput}
                    </label>
                    <label className="flex flex-col text-xs">
                        <span className="text-label-3 px-2.5">MJ</span>
                        {unitInput}
                    </label>
                    <label className="flex flex-col text-xs">
                        <span className="text-label-3 px-2.5">Cena za MJ</span>
                        {priceInput}
                    </label>
                    <div className="flex flex-col text-xs">
                        <span className="text-label-3 px-2.5">Celkem</span>
                        <span className="flex h-9 items-center justify-end px-2.5 text-[14px] font-medium tabular-nums">
                            {totalText}
                        </span>
                    </div>
                </div>
                {errorText}
            </div>
        );
    }

    return (
        <tr>
            <td className="!px-1 !py-1 align-top">
                <div className="flex flex-col pt-[7px]">
                    {nameInput}
                    {description}
                    {errorText}
                </div>
            </td>
            <td className="!px-1">{qtyInput}</td>
            <td className="!px-1">{unitInput}</td>
            <td className="!px-1">{priceInput}</td>
            <td className="is-num font-medium">{totalText}</td>
            <td>
                <button
                    type="button"
                    className="af-btn af-btn--icon af-btn--sm"
                    aria-label="Odebrat položku"
                    tabIndex={-1}
                    onClick={onRemove}
                >
                    <X strokeWidth={2} aria-hidden="true" />
                </button>
            </td>
        </tr>
    );
}
