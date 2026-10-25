import SettingsRow, { SectionTitle, Toggle } from '@/components/settings/SettingsRow';
import type { CompanyValues } from '@/lib/settings';

type CompanySectionProps = {
    values: CompanyValues;
    onChange: <K extends keyof CompanyValues>(field: K, value: CompanyValues[K]) => void;
    error: (field: string) => string | undefined;
    onLoadAres: () => void;
    aresBusy: boolean;
    aresError?: string;
};

const invalid = (message?: string) => (message ? 'af-input af-input--error' : 'af-input');

export default function CompanySection({
    values,
    onChange,
    error,
    onLoadAres,
    aresBusy,
    aresError,
}: CompanySectionProps) {
    const text = (field: keyof CompanyValues & string) => ({
        id: `s-${field}`,
        value: values[field] as string,
        onChange: (event: { target: { value: string } }) =>
            onChange(field, event.target.value as never),
        className: invalid(error(`company.${field}`)),
        'aria-invalid': error(`company.${field}`) ? true : undefined,
    });

    return (
        <section id="firma" aria-labelledby="h-firma" className="scroll-mt-5">
            <SectionTitle id="h-firma">Firma</SectionTitle>
            <div className="af-list">
                <SettingsRow
                    label="Jméno a příjmení"
                    htmlFor="s-name"
                    error={error('company.name')}
                >
                    <input {...text('name')} autoComplete="organization" />
                </SettingsRow>

                <SettingsRow label="IČO" htmlFor="s-ico" error={error('company.ico') ?? aresError}>
                    <div className="flex min-w-0 gap-2">
                        <input
                            {...text('ico')}
                            inputMode="numeric"
                            className={`${invalid(error('company.ico') ?? aresError)} af-mono max-w-[200px]`}
                        />
                        <button
                            type="button"
                            className="af-btn af-btn--tinted shrink-0"
                            disabled={aresBusy}
                            onClick={onLoadAres}
                        >
                            {aresBusy ? 'Načítám…' : 'Načíst z ARES'}
                        </button>
                    </div>
                </SettingsRow>

                <SettingsRow label="DIČ" htmlFor="s-dic" error={error('company.dic')}>
                    <input
                        {...text('dic')}
                        placeholder="Nemám"
                        className={`${invalid(error('company.dic'))} af-mono`}
                    />
                </SettingsRow>

                <SettingsRow
                    label="Ulice a číslo"
                    htmlFor="s-street"
                    error={error('company.street')}
                >
                    <input {...text('street')} autoComplete="street-address" />
                </SettingsRow>

                <SettingsRow
                    label="PSČ a obec"
                    htmlFor="s-zip"
                    error={error('company.zip') ?? error('company.city')}
                >
                    <div className="grid grid-cols-[110px_minmax(0,1fr)] gap-2">
                        <input {...text('zip')} aria-label="PSČ" autoComplete="postal-code" />
                        <input {...text('city')} aria-label="Obec" autoComplete="address-level2" />
                    </div>
                </SettingsRow>

                <SettingsRow
                    label="Údaj o zápisu"
                    htmlFor="s-registration_note"
                    error={error('company.registration_note')}
                >
                    <input {...text('registration_note')} />
                </SettingsRow>

                <SettingsRow label="Plátce DPH">
                    <div className="flex items-center gap-3">
                        <Toggle checked={values.vat_payer} label="Plátce DPH" disabled />
                        <span className="text-label-3 text-[13px]">
                            Na dokladech se tiskne „Nejsem plátce DPH.“ Přepnutí na plátce zatím
                            není k dispozici.
                        </span>
                    </div>
                </SettingsRow>

                <SettingsRow
                    label="E-mail, telefon, web"
                    htmlFor="s-email"
                    error={error('company.email') ?? error('company.phone') ?? error('company.web')}
                >
                    <div className="tablet:grid-cols-3 grid grid-cols-1 gap-2">
                        <input
                            {...text('email')}
                            type="email"
                            aria-label="E-mail"
                            autoComplete="email"
                        />
                        <input
                            {...text('phone')}
                            type="tel"
                            aria-label="Telefon"
                            autoComplete="tel"
                        />
                        <input {...text('web')} aria-label="Web" autoComplete="url" />
                    </div>
                </SettingsRow>

                <SettingsRow label="Logo na dokladech">
                    <div className="flex flex-wrap items-center gap-3">
                        <span
                            className="af-sidebar__mark"
                            style={{ width: 44, height: 44, borderRadius: 12, fontSize: 16 }}
                            aria-hidden="true"
                        >
                            AF
                        </span>
                        <button type="button" className="af-btn" disabled>
                            Nahrát SVG nebo PNG
                        </button>
                        <span className="text-label-3 text-xs">Zatím zástupné logo</span>
                    </div>
                </SettingsRow>
            </div>
        </section>
    );
}
