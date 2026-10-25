/**
 * IBAN z českého čísla účtu (náhled ve formuláři; platný je výpočet na serveru).
 * Vrací null, dokud číslo účtu nebo kód banky nemají správný tvar.
 */
export function czechIban(accountNumber: string, bankCode: string): string | null {
    const match = /^(?:(\d{1,6})-)?(\d{2,10})$/.exec(accountNumber.replace(/\s+/g, ''));
    const bank = bankCode.trim();
    if (!match || !/^\d{4}$/.test(bank)) return null;

    const bban = bank + (match[1] ?? '').padStart(6, '0') + match[2].padStart(10, '0');
    const check = 98 - Number(BigInt(`${bban}123500`) % 97n);

    return `CZ${String(check).padStart(2, '0')}${bban}`;
}

/** IBAN po čtveřicích („CZ15 0800 0000 0024 0123 4567“). */
export function prettyIban(iban: string): string {
    return iban
        .replace(/\s+/g, '')
        .replace(/(.{4})/g, '$1 ')
        .trim();
}
