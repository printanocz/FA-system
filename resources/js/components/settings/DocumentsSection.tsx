import SettingsRow, { SectionTitle, Toggle } from '@/components/settings/SettingsRow';
import type { DocumentValues } from '@/lib/settings';

type DocumentsSectionProps = {
    values: DocumentValues;
    onChange: <K extends keyof DocumentValues>(field: K, value: DocumentValues[K]) => void;
    error: (field: string) => string | undefined;
};

const PRESETS = [7, 14, 30];

export default function DocumentsSection({ values, onChange, error }: DocumentsSectionProps) {
    const custom = !PRESETS.includes(values.due_days);
    const dueError = error('documents.due_days');
    const field = (name: string) =>
        error(`documents.${name}`) ? 'af-input af-input--error' : 'af-input';

    return (
        <section id="doklady" aria-labelledby="h-doklady" className="scroll-mt-5">
            <SectionTitle id="h-doklady">Doklady a texty</SectionTitle>
            <div className="af-list">
                <SettingsRow label="Výchozí splatnost" error={dueError}>
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="af-segmented" role="group" aria-label="Výchozí splatnost">
                            {PRESETS.map((days) => (
                                <button
                                    key={days}
                                    type="button"
                                    aria-pressed={values.due_days === days}
                                    onClick={() => onChange('due_days', days)}
                                >
                                    {days} dní
                                </button>
                            ))}
                            <button
                                type="button"
                                aria-pressed={custom}
                                onClick={() => !custom && onChange('due_days', 21)}
                            >
                                Jiná
                            </button>
                        </div>
                        {custom && (
                            <div className="af-input-wrap w-[120px]">
                                <input
                                    className={`${field('due_days')} af-input--amount`}
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    max={365}
                                    aria-label="Vlastní splatnost ve dnech"
                                    value={Number.isNaN(values.due_days) ? '' : values.due_days}
                                    onChange={(event) =>
                                        onChange(
                                            'due_days',
                                            event.target.value === ''
                                                ? NaN
                                                : Number(event.target.value),
                                        )
                                    }
                                />
                                <span className="af-input-wrap__suffix">dní</span>
                            </div>
                        )}
                    </div>
                </SettingsRow>

                <SettingsRow
                    label="Konstantní symbol"
                    htmlFor="s-ks"
                    error={error('documents.constant_symbol')}
                >
                    <input
                        id="s-ks"
                        className={`${field('constant_symbol')} af-mono max-w-[120px]`}
                        inputMode="numeric"
                        value={values.constant_symbol}
                        onChange={(event) => onChange('constant_symbol', event.target.value)}
                    />
                </SettingsRow>

                <SettingsRow label="Zaokrouhlovat na koruny">
                    <Toggle
                        checked={values.round_total}
                        label="Zaokrouhlovat na koruny"
                        onChange={(checked) => onChange('round_total', checked)}
                    />
                </SettingsRow>

                <SettingsRow
                    label="Text před položkami"
                    htmlFor="s-before"
                    error={error('documents.text_before')}
                >
                    <input
                        id="s-before"
                        className={field('text_before')}
                        value={values.text_before}
                        onChange={(event) => onChange('text_before', event.target.value)}
                    />
                </SettingsRow>

                <SettingsRow
                    label="Text za položkami"
                    htmlFor="s-after"
                    alignTop
                    error={error('documents.text_after')}
                >
                    <textarea
                        id="s-after"
                        rows={2}
                        className={field('text_after')}
                        style={{
                            height: 'auto',
                            padding: '8px 12px',
                            lineHeight: '20px',
                            resize: 'vertical',
                        }}
                        value={values.text_after}
                        onChange={(event) => onChange('text_after', event.target.value)}
                    />
                </SettingsRow>
            </div>
            <div className="af-list-footer">
                Platí pro nové doklady. Na konceptu jdou texty změnit.
            </div>
        </section>
    );
}
