/** Částka z API (řetězec s tečkou) česky: „182 400 Kč“, s haléři jen když nějaké jsou. */
export function formatMoney(amount: string | number | null | undefined, currency = 'CZK'): string {
    const value = Number(amount ?? 0);
    const whole = Math.abs(value - Math.round(value)) < 0.005;

    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency,
        minimumFractionDigits: whole ? 0 : 2,
        maximumFractionDigits: whole ? 0 : 2,
    }).format(value);
}

/** Datum `YYYY-MM-DD` česky. Bez roku: „26. 10.“, s rokem: „26. 10. 2026“. */
export function formatDate(date: string, withYear = true): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Intl.DateTimeFormat('cs-CZ', {
        day: 'numeric',
        month: 'numeric',
        ...(withYear ? { year: 'numeric' } : {}),
    }).format(new Date(year, month - 1, day));
}

/** Český tvar podle počtu: 1 faktura, 2–4 faktury, 0 a 5+ faktur. */
export function plural(count: number, one: string, few: string, many: string): string {
    if (count === 1) return one;
    if (count >= 2 && count <= 4) return few;

    return many;
}

/** Iniciály z názvu: první písmena prvních dvou slov bez právní formy („Pekárna Hrubý s.r.o.“ → PH). */
export function initials(name: string): string {
    const words = name
        .trim()
        .split(/\s+/)
        .filter(
            (word) =>
                /\p{L}/u.test(word[0] ?? '') &&
                !/^(s\.r\.o\.|a\.s\.|z\.|s\.|v\.o\.s\.|k\.s\.)$/i.test(word),
        );

    return (
        words
            .slice(0, 2)
            .map((word) => word[0].toUpperCase())
            .join('') || '?'
    );
}
