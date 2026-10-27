import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ContactCardData } from '@/lib/contacts';
import { documentBadge, documentTypeLabel } from '@/lib/documents';
import { formatDate, formatMoney, initials, plural } from '@/lib/format';

type ContactCardProps = {
    contact: ContactCardData;
    defaultDueDays: number;
    onEdit: () => void;
    onToast: (message: string) => void;
};

const dash = '—';

function Stat({ label, value, note }: { label: string; value: string; note: string }) {
    return (
        <div className="af-card flex flex-col gap-1 p-4">
            <span className="text-label-2 text-[13px]">{label}</span>
            <span className="text-[22px] leading-7 font-semibold tabular-nums">{value}</span>
            <span className="text-label-3 text-xs">{note}</span>
        </div>
    );
}

function Row({
    label,
    children,
    top,
}: {
    label: string;
    children: React.ReactNode;
    top?: boolean;
}) {
    return (
        <div className="af-list__row" style={top ? { alignItems: 'flex-start' } : undefined}>
            <span className="af-list__label !flex-none">{label}</span>
            <span className="af-list__value min-w-0 flex-1 break-words">{children}</span>
        </div>
    );
}

/** Karta vybraného kontaktu: hlavička, tři čísla, údaje, výchozí hodnoty a poslední doklady. */
export default function ContactCard({
    contact,
    defaultDueDays,
    onEdit,
    onToast,
}: ContactCardProps) {
    const [refreshing, setRefreshing] = useState(false);
    const { stats } = contact;

    const refreshFromAres = () =>
        router.post(
            `/adresar/${contact.id}/ares`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setRefreshing(true),
                onFinish: () => setRefreshing(false),
                onSuccess: () => onToast('Aktualizováno z ARES'),
                onError: (errors) => onToast(errors.ico ?? 'ARES se nepodařilo načíst.'),
            },
        );

    const restore = () =>
        router.post(
            `/adresar/${contact.id}/obnovit`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => onToast('Kontakt obnoven'),
            },
        );

    const address = [contact.street, [contact.zip, contact.city].filter(Boolean).join(' ')]
        .filter(Boolean)
        .concat(contact.country !== 'CZ' ? [contact.country] : []);

    const dueDays =
        contact.default_due_days === null
            ? `${defaultDueDays} ${plural(defaultDueDays, 'den', 'dny', 'dní')} (z Nastavení)`
            : `${contact.default_due_days} ${plural(contact.default_due_days, 'den', 'dny', 'dní')}`;

    return (
        <section
            className="flex min-w-0 flex-[999_1_480px] flex-col gap-5"
            aria-labelledby="h-contact"
        >
            <div className="flex flex-wrap items-center gap-4">
                <span
                    className="ct-avatar"
                    aria-hidden="true"
                    style={{ width: 56, height: 56, fontSize: 20 }}
                >
                    {initials(contact.name)}
                </span>
                <div className="mr-auto flex min-w-0 flex-col gap-0.5">
                    <h2
                        id="h-contact"
                        className="m-0 text-[22px] leading-7 font-bold tracking-[-0.2px] break-words"
                    >
                        {contact.name}
                    </h2>
                    <span className="text-label-3 flex flex-wrap items-center gap-2 text-[13px]">
                        {contact.archived && (
                            <span className="af-badge af-badge--outline">Archivováno</span>
                        )}
                        {contact.ares_synced_at && (
                            <span>Ověřeno v ARES {formatDate(contact.ares_synced_at)}</span>
                        )}
                    </span>
                </div>
                <button type="button" className="af-btn" onClick={onEdit}>
                    Upravit
                </button>
                {contact.archived ? (
                    <button type="button" className="af-btn af-btn--tinted" onClick={restore}>
                        Obnovit
                    </button>
                ) : (
                    <Link
                        href={`/faktury/nova?kontakt=${contact.id}`}
                        className="af-btn af-btn--tinted no-underline"
                    >
                        Nová faktura
                    </Link>
                )}
            </div>

            <div className="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-3">
                <Stat
                    label={`Obrat ${stats.year}`}
                    value={formatMoney(stats.turnover)}
                    note={`${stats.invoice_count} ${plural(stats.invoice_count, 'faktura', 'faktury', 'faktur')}`}
                />
                <Stat
                    label="Neuhrazeno"
                    value={formatMoney(stats.unpaid)}
                    note={
                        stats.unpaid_next_due
                            ? `splatnost ${formatDate(stats.unpaid_next_due, false)}`
                            : 'nic nečeká na úhradu'
                    }
                />
                <Stat
                    label="Platí průměrně"
                    value={
                        stats.average_payment_days === null
                            ? dash
                            : `${stats.average_payment_days} ${plural(stats.average_payment_days, 'den', 'dny', 'dní')}`
                    }
                    note={
                        stats.average_payment_days === null
                            ? 'zatím bez uhrazených faktur'
                            : 'od vystavení'
                    }
                />
            </div>

            <div className="flex flex-wrap items-start gap-5">
                <div className="min-w-0 flex-[1_1_280px]">
                    <div className="af-list-header">Fakturační údaje</div>
                    <div className="af-list">
                        <Row label="IČ">
                            <span className="af-mono">{contact.ico || dash}</span>
                        </Row>
                        <Row label="DIČ">
                            <span className="af-mono">{contact.dic || dash}</span>
                        </Row>
                        <Row label="Adresa" top>
                            {address.length === 0
                                ? dash
                                : address.map((line, index) => (
                                      <span key={line}>
                                          {index > 0 && <br />}
                                          {line}
                                      </span>
                                  ))}
                        </Row>
                        {contact.ico && (
                            <div className="af-list__row">
                                <button
                                    type="button"
                                    className="af-btn af-btn--plain af-btn--sm p-0"
                                    disabled={refreshing}
                                    onClick={refreshFromAres}
                                >
                                    Aktualizovat z ARES
                                </button>
                            </div>
                        )}
                    </div>
                </div>
                <div className="min-w-0 flex-[1_1_280px]">
                    <div className="af-list-header">Kontakt a výchozí hodnoty</div>
                    <div className="af-list">
                        <Row label="E-mail pro faktury">{contact.email || dash}</Row>
                        <Row label="Kopie">
                            {contact.email_cc.length ? contact.email_cc.join(', ') : dash}
                        </Row>
                        <Row label="Telefon">{contact.phone || dash}</Row>
                        <Row label="Splatnost">{dueDays}</Row>
                        {contact.default_discount_percent !== null &&
                            Number(contact.default_discount_percent) > 0 && (
                                <Row label="Sleva">
                                    {Number(contact.default_discount_percent).toLocaleString(
                                        'cs-CZ',
                                    )}{' '}
                                    %
                                </Row>
                            )}
                        {contact.note && (
                            <Row label="Poznámka" top>
                                {contact.note}
                            </Row>
                        )}
                    </div>
                </div>
            </div>

            <div>
                <div className="af-list-header">Doklady</div>
                <div className="af-list">
                    {contact.documents.length === 0 && (
                        <div className="af-list__row text-label-3">Zatím žádný doklad.</div>
                    )}
                    {contact.documents.map((document) => {
                        const badge = documentBadge(document.status, document.computed_status);

                        return (
                            <div key={document.id} className="af-list__row">
                                <span className="af-mono w-[76px] shrink-0">
                                    {document.number ?? dash}
                                </span>
                                <span className="af-list__label text-label-2">
                                    {document.description ?? documentTypeLabel(document.type)}
                                </span>
                                <span className="font-semibold whitespace-nowrap tabular-nums">
                                    {formatMoney(document.total, document.currency)}
                                </span>
                                <span className={badge.className}>{badge.label}</span>
                            </div>
                        );
                    })}
                    {contact.documents.length > 0 && (
                        <div className="af-list__row">
                            <button
                                type="button"
                                className="af-btn af-btn--plain af-btn--sm p-0"
                                disabled
                                title="Připravujeme"
                            >
                                Všechny doklady
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
