import {
    ChartColumn,
    ClipboardCheck,
    CornerUpLeft,
    CreditCard,
    FileText,
    House,
    Landmark,
    Receipt,
    Repeat,
    Settings,
    ShoppingBasket,
    Tag,
    UserRound,
    type LucideIcon,
} from 'lucide-react';

export type NavItem = {
    label: string;
    icon: LucideIcon;
    /** Cesta existující obrazovky; bez ní je položka zatím jen zástupná. */
    href?: string;
};

export type NavSection = {
    title?: string;
    items: NavItem[];
};

export const home: NavItem = { label: 'Přehled', icon: House, href: '/' };

export const sales: NavItem[] = [
    { label: 'Faktury vydané', icon: FileText },
    { label: 'Cenové nabídky', icon: ClipboardCheck },
    { label: 'Zálohové faktury', icon: Receipt },
    { label: 'Dobropisy', icon: CornerUpLeft },
    { label: 'Pravidelné faktury', icon: Repeat },
    { label: 'Úhrady', icon: CreditCard },
];

export const records: NavItem[] = [
    { label: 'Nákup', icon: ShoppingBasket },
    { label: 'Adresář', icon: UserRound },
    { label: 'Ceník', icon: Tag },
    { label: 'Banka', icon: Landmark },
    { label: 'Reporty', icon: ChartColumn },
];

export const settings: NavItem = { label: 'Nastavení', icon: Settings };

/** Menu pro Sidebar (desktop a sheet na tabletu). */
export const sidebarSections: NavSection[] = [
    { items: [home] },
    { title: 'Prodej', items: sales },
    { title: 'Evidence', items: records },
];

/** Položky, které se do TabBaru nevejdou: sheet „Další“. */
export const moreSections: NavSection[] = [
    { title: 'Prodej', items: sales.slice(2) },
    { title: 'Evidence', items: records },
    { items: [settings] },
];
