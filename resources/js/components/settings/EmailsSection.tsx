import SettingsRow, { SectionTitle, Toggle } from '@/components/settings/SettingsRow';
import { renderTemplate, SAMPLE_VARIABLES } from '@/lib/emailTemplate';
import type { EmailValues } from '@/lib/settings';

type EmailsSectionProps = {
    values: EmailValues;
    variables: Record<string, string>;
    onChange: <K extends keyof EmailValues>(field: K, value: EmailValues[K]) => void;
    error: (field: string) => string | undefined;
    senderPlaceholder: string;
};

const textareaStyle = {
    height: 'auto',
    padding: '8px 12px',
    lineHeight: '20px',
    resize: 'vertical',
} as const;

function Preview({ subject, body }: { subject: string; body: string }) {
    return (
        <div className="bg-fill flex flex-col gap-2 rounded-[14px] px-4 py-3 text-[14px] leading-5">
            <span className="text-label-3 text-xs leading-4">Náhled na ukázkových datech</span>
            <span className="font-semibold">{renderTemplate(subject, SAMPLE_VARIABLES)}</span>
            <span className="text-label-2 whitespace-pre-line">
                {renderTemplate(body, SAMPLE_VARIABLES)}
            </span>
        </div>
    );
}

export default function EmailsSection({
    values,
    variables,
    onChange,
    error,
    senderPlaceholder,
}: EmailsSectionProps) {
    const field = (name: string) =>
        error(`emails.${name}`) ? 'af-input af-input--error' : 'af-input';

    const template = (
        kind: 'invoice' | 'reminder',
        title: string,
        subjectKey: 'invoice_subject' | 'reminder_subject',
        bodyKey: 'invoice_body' | 'reminder_body',
    ) => (
        <>
            <div className="af-list-header">{title}</div>
            <div className="af-list">
                <SettingsRow
                    label="Předmět"
                    htmlFor={`s-${kind}-subject`}
                    error={error(`emails.${subjectKey}`)}
                >
                    <input
                        id={`s-${kind}-subject`}
                        className={field(subjectKey)}
                        value={values[subjectKey]}
                        onChange={(event) => onChange(subjectKey, event.target.value)}
                    />
                </SettingsRow>
                <SettingsRow
                    label="Text"
                    htmlFor={`s-${kind}-body`}
                    alignTop
                    error={error(`emails.${bodyKey}`)}
                >
                    <textarea
                        id={`s-${kind}-body`}
                        rows={9}
                        className={field(bodyKey)}
                        style={textareaStyle}
                        value={values[bodyKey]}
                        onChange={(event) => onChange(bodyKey, event.target.value)}
                    />
                </SettingsRow>
                <SettingsRow label="Náhled" alignTop>
                    <Preview subject={values[subjectKey]} body={values[bodyKey]} />
                </SettingsRow>
            </div>
        </>
    );

    return (
        <section id="emaily" aria-labelledby="h-emaily" className="scroll-mt-5">
            <SectionTitle id="h-emaily">E-maily a upomínky</SectionTitle>

            <div className="af-list">
                <SettingsRow
                    label="Jméno odesílatele"
                    htmlFor="s-sender"
                    error={error('emails.sender_name')}
                >
                    <input
                        id="s-sender"
                        className={field('sender_name')}
                        placeholder={senderPlaceholder}
                        value={values.sender_name}
                        onChange={(event) => onChange('sender_name', event.target.value)}
                    />
                </SettingsRow>
                <SettingsRow label="Poslat kopii mně">
                    <Toggle
                        checked={values.copy_to_me}
                        label="Poslat kopii mně jako výchozí"
                        onChange={(checked) => onChange('copy_to_me', checked)}
                    />
                </SettingsRow>
            </div>
            <div className="af-list-footer">
                Výchozí stav přepínače v okně Odeslat e-mailem. Kopie jde na e-mail z profilu firmy,
                odpovědi zákazníků také.
            </div>

            {template('invoice', 'Šablona: Faktura', 'invoice_subject', 'invoice_body')}
            {template('reminder', 'Šablona: Upomínka', 'reminder_subject', 'reminder_body')}

            <div className="af-list-header">Proměnné</div>
            <div className="af-list">
                {Object.entries(variables).map(([name, description]) => (
                    <SettingsRow key={name} label={`{${name}}`}>
                        <span className="text-label-2 py-1.5 text-[14px] leading-5">
                            {description}
                        </span>
                    </SettingsRow>
                ))}
            </div>
            <div className="af-list-footer">
                Proměnné se dosadí při otevření okna Odeslat e-mailem a před odesláním jdou texty
                upravit. Podpis ({'{dodavatel}'}) je jméno z profilu firmy.
            </div>
        </section>
    );
}
