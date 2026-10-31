import { Link } from '@inertiajs/react';
import { Toggle } from '@/components/settings/SettingsRow';
import { filterQuery, type ContactListItem } from '@/lib/contacts';
import { formatMoney, initials, plural } from '@/lib/format';

type ContactListProps = {
    contacts: ContactListItem[];
    filters: { q: string; archived: boolean };
    year: number;
    /** Kontakt zvýrazněný v seznamu (jen tam, kde je vedle seznamu vidět karta). */
    currentId: number | null;
    onToggleArchived: (archived: boolean) => void;
};

/** Seznam kontaktů (artboard „Adresář“): počet, řazení, řádky s avatarem, IČ a obratem. */
export default function ContactList({
    contacts,
    filters,
    year,
    currentId,
    onToggleArchived,
}: ContactListProps) {
    const query = filterQuery(filters);

    return (
        <section
            className="af-card flex max-w-[400px] min-w-0 flex-[1_1_340px] flex-col gap-0.5 p-2 max-tablet:max-w-none max-tablet:basis-full"
            aria-label="Seznam kontaktů"
        >
            <div className="text-label-3 flex justify-between px-3 py-2 text-xs">
                <span>
                    {contacts.length} {plural(contacts.length, 'kontakt', 'kontakty', 'kontaktů')}
                </span>
                <span>Řadit: obrat {year}</span>
            </div>

            {contacts.length === 0 && (
                <p className="text-label-3 m-0 px-3 py-6 text-center text-[14px]">
                    {filters.q ? 'Nic nenalezeno.' : 'Zatím žádný kontakt.'}
                </p>
            )}

            {contacts.map((contact) => (
                <Link
                    key={contact.id}
                    href={`/adresar/${contact.id}${query}`}
                    className="ct-row"
                    aria-current={currentId === contact.id ? 'true' : undefined}
                    preserveState
                    preserveScroll
                    only={['selected', 'selectedId']}
                >
                    <span className="ct-avatar" aria-hidden="true">
                        {initials(contact.name)}
                    </span>
                    <span className="flex min-w-0 flex-1 flex-col">
                        <span
                            className={`overflow-hidden font-semibold text-ellipsis whitespace-nowrap ${contact.archived ? 'text-label-3' : ''}`}
                        >
                            {contact.name}
                        </span>
                        <span className="text-label-3 text-xs leading-4">
                            {contact.archived ? (
                                'Archivováno'
                            ) : contact.ico ? (
                                <>
                                    IČ <span className="af-mono text-xs">{contact.ico}</span>
                                </>
                            ) : (
                                'Bez IČ'
                            )}
                        </span>
                    </span>
                    <span className="text-label-2 text-[13px] whitespace-nowrap tabular-nums">
                        {formatMoney(contact.turnover ?? 0)}
                    </span>
                </Link>
            ))}

            <div className="flex items-center justify-between gap-3 px-3 pt-3 pb-2 text-[14px]">
                <span>Zobrazit archivované</span>
                <Toggle
                    checked={filters.archived}
                    label="Zobrazit archivované"
                    onChange={onToggleArchived}
                />
            </div>
        </section>
    );
}
