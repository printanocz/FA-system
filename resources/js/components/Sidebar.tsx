import { Link, usePage } from '@inertiajs/react';
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

type NavItem = {
    label: string;
    icon: LucideIcon;
    /** Cesta existující obrazovky; bez ní je položka zatím jen zástupná. */
    href?: string;
};

type NavSection = {
    title?: string;
    items: NavItem[];
};

const sections: NavSection[] = [
    { items: [{ label: 'Přehled', icon: House, href: '/' }] },
    {
        title: 'Prodej',
        items: [
            { label: 'Faktury vydané', icon: FileText },
            { label: 'Cenové nabídky', icon: ClipboardCheck },
            { label: 'Zálohové faktury', icon: Receipt },
            { label: 'Dobropisy', icon: CornerUpLeft },
            { label: 'Pravidelné faktury', icon: Repeat },
            { label: 'Úhrady', icon: CreditCard },
        ],
    },
    {
        title: 'Evidence',
        items: [
            { label: 'Nákup', icon: ShoppingBasket },
            { label: 'Adresář', icon: UserRound },
            { label: 'Ceník', icon: Tag },
            { label: 'Banka', icon: Landmark },
            { label: 'Reporty', icon: ChartColumn },
        ],
    },
];

const settings: NavItem = { label: 'Nastavení', icon: Settings };

function NavLink({ item, current }: { item: NavItem; current: boolean }) {
    const Icon = item.icon;
    const content = (
        <>
            <Icon strokeWidth={1.75} aria-hidden="true" />
            {item.label}
        </>
    );

    if (item.href) {
        return (
            <Link
                href={item.href}
                className="af-sidebar__item"
                aria-current={current ? 'page' : undefined}
            >
                {content}
            </Link>
        );
    }

    // Obrazovka ještě neexistuje: odkaz nikam nevede.
    return (
        <a href="#" className="af-sidebar__item" onClick={(event) => event.preventDefault()}>
            {content}
        </a>
    );
}

export default function Sidebar({ className = '' }: { className?: string }) {
    const { url } = usePage();
    const path = url.split('?')[0];

    return (
        <nav className={`af-sidebar af-glass ${className}`} aria-label="Hlavní menu">
            <div className="af-sidebar__brand">
                <span className="af-sidebar__mark">AF</span>
                Fakturace
            </div>

            {sections.map((section) => (
                <div key={section.title ?? 'main'} className="flex flex-col gap-0.5">
                    {section.title && <div className="af-sidebar__section">{section.title}</div>}
                    {section.items.map((item) => (
                        <NavLink key={item.label} item={item} current={item.href === path} />
                    ))}
                </div>
            ))}

            <div className="grow" />
            <NavLink item={settings} current={false} />
        </nav>
    );
}
