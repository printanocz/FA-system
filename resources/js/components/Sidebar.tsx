import { Link, usePage } from '@inertiajs/react';
import type { SharedProps } from '@/lib/shared';
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

/** Dvě iniciály z jména („Adam Fuják“ → „AF“). */
function initials(name: string): string {
    const words = name.trim().split(/\s+/).filter(Boolean);
    const letters = words.length > 1 ? [words[0], words[words.length - 1]] : words.slice(0, 1);
    return letters.map((word) => word[0].toUpperCase()).join('') || '?';
}

/** Řádek přihlášeného účtu dole v Sidebaru: iniciály, jméno a e-mail; vede na sekci Účet. */
function AccountRow({ onNavigate }: { onNavigate?: () => void }) {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user;
    if (!user) return null;

    return (
        <Link href="/nastaveni#ucet" className="af-sidebar__item h-auto py-2" onClick={onNavigate}>
            <span
                aria-hidden="true"
                className="bg-fill-strong text-label grid size-[30px] flex-none place-items-center rounded-full text-xs font-bold"
            >
                {initials(user.name)}
            </span>
            <span className="flex min-w-0 flex-col leading-4">
                <span className="text-[13px] font-semibold">{user.name}</span>
                <span className="text-label-3 truncate text-xs">{user.email}</span>
            </span>
        </Link>
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
            <NavLink
                item={settings}
                current={path.startsWith('/nastaveni')}
                onNavigate={onNavigate}
            />
            <div className="bg-separator mx-2.5 my-2 h-px" />
            <AccountRow onNavigate={onNavigate} />
        </nav>
    );
}
