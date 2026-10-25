import { Link, usePage } from '@inertiajs/react';
import { settings, sidebarSections, type NavItem } from '@/lib/navigation';

type SidebarProps = {
    className?: string;
    /** Zavolá se po kliknutí na položku (zavření sheetu na tabletu). */
    onNavigate?: () => void;
};

function NavLink({
    item,
    current,
    onNavigate,
}: {
    item: NavItem;
    current: boolean;
    onNavigate?: () => void;
}) {
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
                onClick={onNavigate}
            >
                {content}
            </Link>
        );
    }

    // Obrazovka ještě neexistuje: odkaz nikam nevede.
    return (
        <a
            href="#"
            className="af-sidebar__item"
            onClick={(event) => {
                event.preventDefault();
                onNavigate?.();
            }}
        >
            {content}
        </a>
    );
}

export default function Sidebar({ className = '', onNavigate }: SidebarProps) {
    const { url } = usePage();
    const path = url.split('?')[0];

    return (
        <nav className={`af-sidebar af-glass ${className}`} aria-label="Hlavní menu">
            <div className="af-sidebar__brand">
                <span className="af-sidebar__mark">AF</span>
                Splatno
            </div>

            {sidebarSections.map((section) => (
                <div key={section.title ?? 'main'} className="flex flex-col gap-0.5">
                    {section.title && <div className="af-sidebar__section">{section.title}</div>}
                    {section.items.map((item) => (
                        <NavLink
                            key={item.label}
                            item={item}
                            current={item.href === path}
                            onNavigate={onNavigate}
                        />
                    ))}
                </div>
            ))}

            <div className="grow" />
            <NavLink item={settings} current={false} onNavigate={onNavigate} />
        </nav>
    );
}
