/** Ukázková data pro náhled šablon v Nastavení (stejné proměnné jako na serveru). */
export const SAMPLE_VARIABLES: Record<string, string> = {
    cislo: '2026061',
    castka: '24 500,00 Kč',
    splatnost: '26. 10. 2026',
    vs: '2026061',
    ucet: '2401234567/0800',
    odberatel: 'Pekárna Hrubý s.r.o.',
    dodavatel: 'Adam Fuják',
    dni_po_splatnosti: '5',
};

/** Dosadí `{proměnné}` do šablony; neznámé zůstanou beze změny (stejně jako na serveru). */
export function renderTemplate(template: string, variables: Record<string, string>): string {
    return template.replace(/\{([a-z_]+)\}/g, (match, name: string) =>
        Object.prototype.hasOwnProperty.call(variables, name) ? variables[name] : match,
    );
}

/** Adresy ze zadaného textu (čárka, středník nebo mezera), bez prázdných a duplicit. */
export function parseAddresses(text: string): string[] {
    return Array.from(new Set(text.split(/[\s,;]+/).filter(Boolean)));
}
