import Sheet from '@/components/Sheet';
import { SEGMENTS, type ListFilters, type Segment } from '@/lib/invoices';

type FilterSheetProps = {
    open: boolean;
    filters: ListFilters;
    counts: Record<Segment, number>;
    years: number[];
    contacts: { id: number; name: string }[];
    onChange: (next: Partial<ListFilters>) => void;
    onClose: () => void;
};

/** Filtry na mobilu (tlačítko „Filtr“ v hlavičce): stav, rok a odběratel. Použijí se hned. */
export default function FilterSheet({
    open,
    filters,
    counts,
    years,
    contacts,
    onChange,
    onClose,
}: FilterSheetProps) {
    return (
        <Sheet open={open} onClose={onClose} side="bottom" label="Filtr faktur">
            <div className="af-sheet min-h-0 overflow-y-auto overscroll-contain">
                <div className="af-sheet__grabber" aria-hidden="true" />
                <h2 className="af-sheet__title">Filtr</h2>

                <div className="af-field">
                    <label className="af-field__label" htmlFor="flt-stav">
                        Stav
                    </label>
                    <select
                        id="flt-stav"
                        className="af-input"
                        value={filters.stav}
                        onChange={(event) => onChange({ stav: event.target.value as Segment })}
                    >
                        {SEGMENTS.map((segment) => (
                            <option key={segment.value} value={segment.value}>
                                {segment.label} ({counts[segment.value]})
                            </option>
                        ))}
                    </select>
                </div>

                <div className="af-field">
                    <label className="af-field__label" htmlFor="flt-rok">
                        Rok
                    </label>
                    <select
                        id="flt-rok"
                        className="af-input"
                        value={filters.rok}
                        onChange={(event) => onChange({ rok: event.target.value })}
                    >
                        <option value="vse">Všechny roky</option>
                        {years.map((year) => (
                            <option key={year} value={String(year)}>
                                {year}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="af-field">
                    <label className="af-field__label" htmlFor="flt-odberatel">
                        Odběratel
                    </label>
                    <select
                        id="flt-odberatel"
                        className="af-input"
                        value={filters.odberatel ?? ''}
                        onChange={(event) =>
                            onChange({
                                odberatel: event.target.value ? Number(event.target.value) : null,
                            })
                        }
                    >
                        <option value="">Všichni odběratelé</option>
                        {contacts.map((contact) => (
                            <option key={contact.id} value={contact.id}>
                                {contact.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="af-sheet__actions">
                    <button type="button" className="af-btn af-btn--prominent" onClick={onClose}>
                        Hotovo
                    </button>
                </div>
            </div>
        </Sheet>
    );
}
