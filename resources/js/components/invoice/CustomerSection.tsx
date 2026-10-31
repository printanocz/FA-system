import { useHttp } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { useSuggest } from '@/hooks/useSuggest';
import { formatDate } from '@/lib/format';
import type { ContactSummary } from '@/lib/invoice';

type CustomerSectionProps = {
    contact: ContactSummary | null;
    error?: string;
    onSelect: (contact: ContactSummary | null) => void;
};

const ARES_ACTION = 'ares';

/** Karta „Odběratel“: combobox nad nearchivovanými kontakty, po výběru karta odběratele. */
export default function CustomerSection({ contact, error, onSelect }: CustomerSectionProps) {
    return (
        <section className="af-card flex flex-col gap-4" aria-labelledby="h-odb">
            <div className="flex items-center justify-between">
                <h2 id="h-odb" className="m-0 text-[20px] leading-[25px] font-semibold">
                    Odběratel
                </h2>
                {contact && (
                    <button
                        type="button"
                        className="af-btn af-btn--plain"
                        onClick={() => onSelect(null)}
                    >
                        Změnit
                    </button>
                )}
            </div>

            {contact ? <CustomerCard contact={contact} /> : <CustomerPicker onSelect={onSelect} />}

            {error && (
                <div className="af-field__error" role="alert">
                    {error}
                </div>
            )}
        </section>
    );
}

function CustomerCard({ contact }: { contact: ContactSummary }) {
    const place = [contact.zip, contact.city].filter(Boolean).join(' ');
    const notes = [
        contact.ares_synced_at && `Údaje ověřené v ARES ${formatDate(contact.ares_synced_at)}.`,
        contact.default_due_days !== null &&
            `Výchozí splatnost klienta ${contact.default_due_days} dní.`,
    ].filter(Boolean);

    return (
        <>
            <div className="flex flex-wrap gap-x-10 gap-y-4">
                <div className="flex min-w-[220px] flex-col gap-0.5">
                    <span className="text-[15px] leading-5 font-semibold">{contact.name}</span>
                    {contact.street && <span className="text-label-2">{contact.street}</span>}
                    {place && <span className="text-label-2">{place}</span>}
                </div>
                <dl className="m-0 grid grid-cols-[auto_auto] content-start gap-x-4 gap-y-1 text-[14px] leading-[19px]">
                    {contact.ico && (
                        <>
                            <dt className="text-label-3">IČ</dt>
                            <dd className="af-mono m-0">{contact.ico}</dd>
                        </>
                    )}
                    {contact.dic && (
                        <>
                            <dt className="text-label-3">DIČ</dt>
                            <dd className="af-mono m-0">{contact.dic}</dd>
                        </>
                    )}
                    {contact.email && (
                        <>
                            <dt className="text-label-3">E-mail</dt>
                            <dd className="m-0">{contact.email}</dd>
                        </>
                    )}
                </dl>
            </div>
            {notes.length > 0 && (
                <p className="text-label-3 m-0 text-xs leading-4">{notes.join(' ')}</p>
            )}
        </>
    );
}

function CustomerPicker({ onSelect }: { onSelect: (contact: ContactSummary) => void }) {
    const listId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const [aresMode, setAresMode] = useState(false);
    const [ico, setIco] = useState('');
    const ares = useHttp<{ ico: string }, { contact: ContactSummary }>('post', '/adresar/z-ares', {
        ico: '',
    });

    const contacts = useSuggest<ContactSummary>('/adresar/hledat', 'contacts', query, open);
    // Poslední volba je vždy „Vyhledat v ARES podle IČO…“.
    const optionCount = contacts.length + 1;

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    const choose = (index: number) => {
        if (index < contacts.length) {
            onSelect(contacts[index]);
            return;
        }
        setIco(/^[\d\s]{6,}$/.test(query) ? query.replace(/\s/g, '') : '');
        setAresMode(true);
        setOpen(false);
    };

    const onKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActive((value) => (value + 1) % optionCount);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((value) => (value - 1 + optionCount) % optionCount);
        } else if (event.key === 'Enter' && open) {
            event.preventDefault();
            choose(Math.min(active, optionCount - 1));
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    };

    const searchAres = async () => {
        ares.clearErrors();
        ares.transform(() => ({ ico }));
        try {
            const result = await ares.post('/adresar/z-ares');
            onSelect(result.contact);
        } catch {
            // Chyba je v ares.errors.ico a zobrazí se pod polem.
        }
    };

    if (aresMode) {
        return (
            <div className="flex flex-col gap-2">
                <div className="af-field">
                    <label className="af-field__label" htmlFor="ares-ico">
                        IČO odběratele
                    </label>
                    <div className="flex flex-wrap gap-2">
                        <input
                            id="ares-ico"
                            className={`af-input af-mono min-w-0 flex-1 ${ares.errors.ico ? 'af-input--error' : ''}`}
                            inputMode="numeric"
                            value={ico}
                            autoFocus
                            placeholder="27082440"
                            autoComplete="off"
                            aria-invalid={ares.errors.ico ? true : undefined}
                            onChange={(event) => setIco(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    void searchAres();
                                }
                            }}
                        />
                        <button
                            type="button"
                            className="af-btn af-btn--tinted"
                            disabled={ares.processing || ico.trim() === ''}
                            onClick={() => void searchAres()}
                        >
                            Najít v ARES
                        </button>
                        <button
                            type="button"
                            className="af-btn af-btn--plain"
                            onClick={() => {
                                ares.clearErrors();
                                setAresMode(false);
                            }}
                        >
                            Zpět
                        </button>
                    </div>
                    {ares.errors.ico ? (
                        <div className="af-field__error" role="alert">
                            {ares.errors.ico}
                        </div>
                    ) : (
                        <div className="af-field__hint">
                            Kontakt se založí z údajů v ARES a rovnou se vybere.
                        </div>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="relative">
            <label className="af-search h-10 w-full">
                <Search strokeWidth={1.75} aria-hidden="true" />
                <input
                    ref={inputRef}
                    type="text"
                    role="combobox"
                    aria-expanded={open}
                    aria-controls={listId}
                    aria-autocomplete="list"
                    aria-activedescendant={open ? `${listId}-${active}` : undefined}
                    aria-label="Odběratel"
                    placeholder="Název nebo IČO odběratele"
                    autoComplete="off"
                    value={query}
                    onFocus={() => setOpen(true)}
                    onBlur={() => window.setTimeout(() => setOpen(false), 120)}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActive(0);
                        setOpen(true);
                    }}
                    onKeyDown={onKeyDown}
                />
            </label>

            {open && (
                <div
                    id={listId}
                    role="listbox"
                    aria-label="Kontakty"
                    className="af-suggest absolute inset-x-0 top-[calc(100%+4px)] z-20"
                >
                    {contacts.map((item, index) => (
                        <div
                            key={item.id}
                            id={`${listId}-${index}`}
                            role="option"
                            aria-selected={active === index}
                            className="af-suggest__item cursor-pointer"
                            onMouseEnter={() => setActive(index)}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                choose(index);
                            }}
                        >
                            <span>{item.name}</span>
                            {item.ico && <span className="af-suggest__meta">{item.ico}</span>}
                        </div>
                    ))}
                    {contacts.length === 0 && (
                        <div className="text-label-3 px-2.5 py-2 text-[14px]">
                            {query ? 'Nikdo takový v adresáři není.' : 'Adresář je zatím prázdný.'}
                        </div>
                    )}
                    <div className="af-suggest__sep" role="separator" />
                    <div
                        id={`${listId}-${contacts.length}`}
                        role="option"
                        aria-selected={active === contacts.length}
                        className="af-suggest__item af-suggest__action cursor-pointer"
                        onMouseEnter={() => setActive(contacts.length)}
                        onMouseDown={(event) => {
                            event.preventDefault();
                            choose(contacts.length);
                        }}
                        data-action={ARES_ACTION}
                    >
                        Vyhledat v ARES podle IČO…
                    </div>
                </div>
            )}
        </div>
    );
}
