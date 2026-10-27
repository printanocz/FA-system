import { router, useForm, useHttp } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import ConfirmSheet from '@/components/ConfirmSheet';
import Sheet from '@/components/Sheet';
import TextField from '@/components/TextField';
import type { ContactCardData } from '@/lib/contacts';

type ContactSheetProps = {
    /** `null` = nový kontakt. */
    contact: ContactCardData | null;
    open: boolean;
    defaultDueDays: number;
    onClose: () => void;
    onToast: (message: string) => void;
};

type AresResult = Partial<
    Record<'name' | 'ico' | 'dic' | 'street' | 'city' | 'zip' | 'country', string>
>;

function ContactForm({
    contact,
    defaultDueDays,
    onClose,
    onToast,
}: Omit<ContactSheetProps, 'open'>) {
    const { data, setData, post, put, processing, errors } = useForm({
        type: contact?.type ?? 'company',
        name: contact?.name ?? '',
        ico: contact?.ico ?? '',
        dic: contact?.dic ?? '',
        street: contact?.street ?? '',
        zip: contact?.zip ?? '',
        city: contact?.city ?? '',
        country: contact?.country ?? 'CZ',
        email: contact?.email ?? '',
        email_cc: contact?.email_cc.join(', ') ?? '',
        phone: contact?.phone ?? '',
        default_due_days: contact?.default_due_days?.toString() ?? '',
        default_discount_percent:
            contact?.default_discount_percent && Number(contact.default_discount_percent) > 0
                ? Number(contact.default_discount_percent).toString()
                : '',
        note: contact?.note ?? '',
    });
    const ares = useHttp<{ ico: string }, AresResult>('post', '/adresar/ares', { ico: '' });
    const [confirmDelete, setConfirmDelete] = useState(false);

    const allErrors = errors as Record<string, string | undefined>;
    // Chyby adres v kopii chodí pod klíči email_cc.0, email_cc.1 …
    const ccError =
        allErrors.email_cc ??
        Object.entries(allErrors).find(([key]) => key.startsWith('email_cc.'))?.[1];

    const options = {
        preserveScroll: true,
        preserveState: true,
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (contact) {
            put(`/adresar/${contact.id}`, {
                ...options,
                onSuccess: () => {
                    onClose();
                    onToast('Uloženo');
                },
            });
        } else {
            post('/adresar', {
                ...options,
                onSuccess: () => {
                    onClose();
                    onToast('Kontakt vytvořen');
                },
            });
        }
    };

    const loadFromAres = async () => {
        ares.clearErrors();
        ares.transform(() => ({ ico: data.ico }));
        try {
            const result = await ares.post('/adresar/ares');
            // ARES vrací jen vyplněné údaje (DIČ často ne), takže prázdné nic nepřepíše.
            setData({ ...data, ...result });
        } catch {
            // Chyba je v ares.errors.ico a zobrazí se u pole IČO.
        }
    };

    const remove = () =>
        router.delete(`/adresar/${contact?.id}`, {
            onSuccess: () => {
                setConfirmDelete(false);
                onClose();
                onToast(contact?.has_documents ? 'Kontakt archivován' : 'Kontakt smazán');
            },
        });

    return (
        <form onSubmit={submit} className="af-sheet min-h-0 overflow-y-auto overscroll-contain">
            <div className="af-sheet__grabber" aria-hidden="true" />
            <h2 className="af-sheet__title">{contact ? 'Upravit kontakt' : 'Nový kontakt'}</h2>

            <div className="af-field">
                <span className="af-field__label" id="ct-type-label">
                    Typ
                </span>
                <div
                    className="af-segmented self-start"
                    role="group"
                    aria-labelledby="ct-type-label"
                >
                    {(
                        [
                            ['company', 'Firma'],
                            ['person', 'Osoba'],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={data.type === value}
                            onClick={() => setData('type', value)}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            <TextField
                label={data.type === 'company' ? 'Název' : 'Jméno a příjmení'}
                value={data.name}
                onChange={(value) => setData('name', value)}
                error={errors.name}
                autoComplete="off"
                required
            />

            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                <TextField
                    label="IČO"
                    value={data.ico}
                    onChange={(value) => setData('ico', value)}
                    error={errors.ico ?? ares.errors.ico}
                    inputMode="numeric"
                    className="af-mono"
                    placeholder="27082440"
                />
                <button
                    type="button"
                    className="af-btn af-btn--tinted mt-[22px]"
                    disabled={ares.processing || data.ico.trim() === ''}
                    onClick={loadFromAres}
                >
                    Načíst z ARES
                </button>
            </div>
            <TextField
                label="DIČ"
                value={data.dic}
                onChange={(value) => setData('dic', value)}
                error={errors.dic}
                className="af-mono"
                placeholder="CZ27082440"
            />

            <TextField
                label="Ulice a číslo"
                value={data.street}
                onChange={(value) => setData('street', value)}
                error={errors.street}
                autoComplete="off"
            />
            <div className="grid grid-cols-[110px_minmax(0,1fr)_72px] gap-3">
                <TextField
                    label="PSČ"
                    value={data.zip}
                    onChange={(value) => setData('zip', value)}
                    error={errors.zip}
                    autoComplete="off"
                />
                <TextField
                    label="Obec"
                    value={data.city}
                    onChange={(value) => setData('city', value)}
                    error={errors.city}
                    autoComplete="off"
                />
                <TextField
                    label="Země"
                    value={data.country}
                    onChange={(value) => setData('country', value.toUpperCase().slice(0, 2))}
                    error={errors.country}
                    className="af-mono"
                    autoComplete="off"
                />
            </div>

            <TextField
                label="E-mail pro faktury"
                type="email"
                value={data.email}
                onChange={(value) => setData('email', value)}
                error={errors.email}
                autoComplete="off"
            />
            <TextField
                label="Kopie"
                value={data.email_cc}
                onChange={(value) => setData('email_cc', value)}
                error={ccError}
                hint="Více adres oddělte čárkou."
                autoComplete="off"
            />
            <TextField
                label="Telefon"
                type="tel"
                value={data.phone}
                onChange={(value) => setData('phone', value)}
                error={errors.phone}
                autoComplete="off"
            />

            <div className="grid grid-cols-2 gap-3">
                <TextField
                    label="Splatnost (dny)"
                    value={data.default_due_days}
                    onChange={(value) => setData('default_due_days', value.replace(/\D/g, ''))}
                    error={errors.default_due_days}
                    inputMode="numeric"
                    placeholder={`Z Nastavení (${defaultDueDays})`}
                />
                <TextField
                    label="Sleva (%)"
                    value={data.default_discount_percent}
                    onChange={(value) => setData('default_discount_percent', value)}
                    error={errors.default_discount_percent}
                    inputMode="decimal"
                    placeholder="0"
                />
            </div>

            <div className="af-field">
                <label className="af-field__label" htmlFor="ct-note">
                    Poznámka
                </label>
                <textarea
                    id="ct-note"
                    rows={3}
                    className={`af-input ${errors.note ? 'af-input--error' : ''}`}
                    style={{
                        height: 'auto',
                        padding: '8px 12px',
                        lineHeight: '20px',
                        resize: 'vertical',
                    }}
                    value={data.note}
                    onChange={(event) => setData('note', event.target.value)}
                />
                {errors.note && (
                    <div className="af-field__error" role="alert">
                        {errors.note}
                    </div>
                )}
            </div>

            <div className="af-sheet__actions">
                {contact && (
                    <button
                        type="button"
                        className="af-btn af-btn--destructive mr-auto"
                        onClick={() => setConfirmDelete(true)}
                    >
                        {contact.has_documents ? 'Archivovat' : 'Smazat'}
                    </button>
                )}
                <button type="button" className="af-btn" onClick={onClose}>
                    Zrušit
                </button>
                <button type="submit" className="af-btn af-btn--prominent" disabled={processing}>
                    Uložit
                </button>
            </div>

            {contact && (
                <ConfirmSheet
                    open={confirmDelete}
                    title={contact.has_documents ? 'Archivovat kontakt?' : 'Smazat kontakt?'}
                    text={
                        contact.has_documents
                            ? 'Kontakt má doklady, proto se nesmaže. Zmizí ze seznamu a najdete ho pod „Zobrazit archivované“.'
                            : 'Kontakt nemá žádný doklad a smaže se nadobro.'
                    }
                    confirmLabel={contact.has_documents ? 'Archivovat' : 'Smazat kontakt'}
                    destructive
                    onConfirm={remove}
                    onCancel={() => setConfirmDelete(false)}
                />
            )}
        </form>
    );
}

/** Sheet pro nový kontakt nebo úpravu kontaktu. Formulář se při každém otevření vytvoří znovu. */
export default function ContactSheet({ open, ...rest }: ContactSheetProps) {
    return (
        <Sheet open={open} onClose={rest.onClose} side="bottom" label="Kontakt">
            {open && <ContactForm key={rest.contact?.id ?? 'new'} {...rest} />}
        </Sheet>
    );
}
