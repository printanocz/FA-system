import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ContactCard from '@/components/contacts/ContactCard';
import ContactList from '@/components/contacts/ContactList';
import ContactSheet from '@/components/contacts/ContactSheet';
import { useToast } from '@/components/Toast';
import Toolbar from '@/components/Toolbar';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { MOBILE_QUERY } from '@/lib/breakpoints';
import { filterQuery, type ContactsPageProps } from '@/lib/contacts';

const SEARCH_DELAY_MS = 250;

export default function ContactsIndex({
    contacts,
    filters,
    year,
    selectedId,
    selected,
    defaultDueDays,
}: ContactsPageProps) {
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const { show, toast } = useToast();
    const [query, setQuery] = useState(filters.q);
    const [sheet, setSheet] = useState<'new' | 'edit' | null>(null);
    const lastSent = useRef(filters.q);

    const visit = (next: { q: string; archived: boolean }) => {
        const params: Record<string, string> = {};
        if (next.q) params.q = next.q;
        if (next.archived) params.archivovane = '1';

        router.get(window.location.pathname, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['contacts', 'filters', 'selected', 'selectedId'],
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

    // Na mobilu je karta samostatná obrazovka, jen když je kontakt zvolený v adrese.
    const showCard = selected !== null && (!isMobile || selectedId !== null);
    const showList = !isMobile || selectedId === null;

    return (
        <>
            <Head title="Adresář" />

            {showList && (
                <Toolbar title="Adresář">
                    <label className="af-search w-[260px] max-w-full max-tablet:order-last max-tablet:w-full">
                        <Search strokeWidth={1.75} aria-hidden="true" />
                        <input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Název, IČO, e-mail"
                            aria-label="Hledat v adresáři"
                            autoComplete="off"
                        />
                    </label>
                    <button
                        type="button"
                        className={`af-btn af-btn--prominent ${isMobile ? 'af-btn--icon' : ''}`}
                        aria-label={isMobile ? 'Nový kontakt' : undefined}
                        onClick={() => setSheet('new')}
                    >
                        <Plus strokeWidth={2} aria-hidden="true" />
                        {!isMobile && 'Nový kontakt'}
                    </button>
                </Toolbar>
            )}

            {isMobile && selectedId !== null && (
                <Link
                    href={`/adresar${filterQuery(filters)}`}
                    preserveState
                    preserveScroll
                    only={['selected', 'selectedId']}
                    className="text-accent-text inline-flex items-center gap-1 self-start text-[15px] no-underline"
                >
                    <ChevronLeft className="size-4" strokeWidth={2} aria-hidden="true" />
                    Adresář
                </Link>
            )}

            <div className="flex flex-wrap items-start gap-5">
                {showList && (
                    <ContactList
                        contacts={contacts}
                        filters={filters}
                        year={year}
                        currentId={isMobile ? null : (selected?.id ?? null)}
                        onToggleArchived={(archived) => visit({ q: filters.q, archived })}
                    />
                )}
                {showCard && selected && (
                    <ContactCard
                        key={selected.id}
                        contact={selected}
                        defaultDueDays={defaultDueDays}
                        onEdit={() => setSheet('edit')}
                        onToast={show}
                    />
                )}
                {!showCard && !isMobile && (
                    <p className="text-label-3 m-0 flex-[999_1_480px] py-10 text-center text-[14px]">
                        Vyberte kontakt nebo vytvořte nový.
                    </p>
                )}
            </div>

            <ContactSheet
                open={sheet !== null}
                contact={sheet === 'edit' ? selected : null}
                defaultDueDays={defaultDueDays}
                onClose={() => setSheet(null)}
                onToast={show}
            />
            {toast}
        </>
    );
}
