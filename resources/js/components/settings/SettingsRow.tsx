import type { ReactNode } from 'react';

type SettingsRowProps = {
    label: string;
    /** `id` pole, ke kterému se popisek váže (jinak se vykreslí jako obyčejný text). */
    htmlFor?: string;
    error?: string;
    alignTop?: boolean;
    children: ReactNode;
};

/** Řádek formuláře Nastavení: popisek vlevo, ovládací prvek vpravo, chyba pod ním. */
export default function SettingsRow({
    label,
    htmlFor,
    error,
    alignTop,
    children,
}: SettingsRowProps) {
    return (
        <div className="set-row" style={alignTop ? { alignItems: 'start' } : undefined}>
            {htmlFor ? (
                <label
                    className="set-label"
                    htmlFor={htmlFor}
                    style={alignTop ? { paddingTop: 8 } : undefined}
                >
                    {label}
                </label>
            ) : (
                <span className="set-label">{label}</span>
            )}
            <div className="flex min-w-0 flex-col gap-1.5">
                {children}
                {error && (
                    <div className="af-field__error" role="alert">
                        {error}
                    </div>
                )}
            </div>
        </div>
    );
}

export function SectionTitle({ id, children }: { id: string; children: ReactNode }) {
    return (
        <h2
            id={id}
            className="af-list-header text-label m-0 pb-2 text-[15px] leading-5 font-semibold"
        >
            {children}
        </h2>
    );
}

/** Přepínač (af-toggle) s přístupným názvem. */
export function Toggle({
    checked,
    onChange,
    label,
    disabled,
}: {
    checked: boolean;
    onChange?: (checked: boolean) => void;
    label: string;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            className="af-toggle disabled:cursor-not-allowed disabled:opacity-50"
            onClick={() => onChange?.(!checked)}
        />
    );
}
