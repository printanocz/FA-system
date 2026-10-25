import { useId, type InputHTMLAttributes } from 'react';

type TextFieldProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'value'> & {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    hint?: string;
};

/** Textové pole z design systému (af-field): popisek, vstup a chyba nebo nápověda pod ním. */
export default function TextField({
    label,
    value,
    onChange,
    error,
    hint,
    id,
    className = '',
    ...rest
}: TextFieldProps) {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const messageId = `${inputId}-message`;

    return (
        <div className="af-field">
            <label className="af-field__label" htmlFor={inputId}>
                {label}
            </label>
            <input
                {...rest}
                id={inputId}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={`af-input ${error ? 'af-input--error' : ''} ${className}`}
                aria-invalid={error ? true : undefined}
                aria-describedby={error || hint ? messageId : undefined}
            />
            {error ? (
                <div id={messageId} className="af-field__error" role="alert">
                    {error}
                </div>
            ) : hint ? (
                <div id={messageId} className="af-field__hint">
                    {hint}
                </div>
            ) : null}
        </div>
    );
}
