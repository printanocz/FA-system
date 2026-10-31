/**
 * Dnešní datum česky, s velkým počátečním písmenem: „Neděle 25. října 2026“.
 * Bez roku (mobil): „Neděle 25. října“.
 */
export function formatToday(withYear = true): string {
    const text = new Intl.DateTimeFormat('cs-CZ', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        ...(withYear ? { year: 'numeric' } : {}),
    }).format(new Date());

    return text.charAt(0).toUpperCase() + text.slice(1);
}
