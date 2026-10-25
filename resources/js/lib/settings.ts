export type CompanyValues = {
    name: string;
    ico: string;
    dic: string;
    street: string;
    zip: string;
    city: string;
    registration_note: string;
    vat_payer: boolean;
    email: string;
    phone: string;
    web: string;
};

export type DocumentValues = {
    due_days: number;
    constant_symbol: string;
    round_total: boolean;
    text_before: string;
    text_after: string;
};

export type BankAccountData = {
    id: number;
    name: string;
    account_number: string;
    bank_code: string;
    iban: string;
    swift: string;
    currency: string;
    is_default: boolean;
};

export type NumberSeriesData = {
    id: number;
    name: string;
    doc_type: string;
    prefix: string;
    format: string;
    next_number: number;
    reset_yearly: boolean;
    is_default: boolean;
    preview: string;
};

export type AccountData = {
    name: string;
    email: string;
    twoFactor: boolean;
};

export type SettingsPageProps = {
    company: CompanyValues;
    documents: DocumentValues;
    bankAccounts: BankAccountData[];
    numberSeries: NumberSeriesData[];
    account: AccountData;
};

export type SectionId =
    'firma' | 'ucty' | 'rady' | 'doklady' | 'emaily' | 'vzhled' | 'ucet' | 'zaloha';

export type SectionDef = {
    id: SectionId;
    label: string;
    /** Sekce bez obsahu: v navigaci jen s popiskem „Připravujeme“. */
    soon?: boolean;
};

export const sections: SectionDef[] = [
    { id: 'firma', label: 'Firma' },
    { id: 'ucty', label: 'Bankovní účty' },
    { id: 'rady', label: 'Číselné řady' },
    { id: 'doklady', label: 'Doklady a texty' },
    { id: 'emaily', label: 'E-maily a upomínky', soon: true },
    { id: 'vzhled', label: 'Vzhled', soon: true },
    { id: 'ucet', label: 'Účet a zabezpečení' },
    { id: 'zaloha', label: 'Záloha a export dat', soon: true },
];

/** Počet změněných polí oproti uloženým hodnotám. */
export function countChanges(current: object, saved: object): number {
    const a = current as Record<string, unknown>;
    const b = saved as Record<string, unknown>;
    return Object.keys(a).filter((key) => String(a[key] ?? '') !== String(b[key] ?? '')).length;
}
