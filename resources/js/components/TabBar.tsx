import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Plus, type LucideIcon } from 'lucide-react';
import { useState } from 'react';
import MoreSheet from '@/components/MoreSheet';
import { useScrollCompact } from '@/hooks/useScrollCompact';
import { home, sales } from '@/lib/navigation';

type Tab = {
    label: string;
    icon: LucideIcon;
    href?: string;
};

// Cíle a ikony se berou z navigation.ts (jediný zdroj menu); mění se jen zkrácený popisek.
const [invoices, quotes] = sales;

const tabs: Tab[] = [
    { label: home.label, icon: home.icon, href: home.href },
    { label: 'Faktury', icon: invoices.icon, href: invoices.href },
    { label: 'Nabídky', icon: quotes.icon, href: quotes.href },
];

/** Záložka je aktivní na své adrese i na podstránkách (`/faktury/12`); Přehled jen na `/`. */
function isCurrent(href: string, path: string): boolean {
    if (href === '/') return path === '/';

    return path === href || path.startsWith(`${href}/`);
}

function TabLabel({ icon: Icon, label }: { icon: LucideIcon; label: string }) {
    return (
        <>
            <Icon strokeWidth={1.75} aria-hidden="true" />
            <span className="max-h-3 overflow-hidden transition-[max-height,opacity] duration-200 group-data-[compact=true]/tab:max-h-0 group-data-[compact=true]/tab:opacity-0 motion-reduce:transition-none">
                {label}
            </span>
        </>
    );
}

/**
 * Mobilní navigace (komponenta TabBar): plovoucí skleněná kapsle Přehled / Faktury /
 * Nabídky / Další a samostatné kulaté tlačítko „Nová faktura“. Při scrollu dolů se
 * kapsle zmenší na ikony. Vykresluje se jen pod 760 px.
 */
export default function TabBar() {
    const { url } = usePage();
    const path = url.split('?')[0];
    const compact = useScrollCompact();
    const [moreOpen, setMoreOpen] = useState(false);

    return (
        <>
            <nav
                className="af-tabbar group/tab fixed inset-x-4 bottom-[calc(env(safe-area-inset-bottom)+--spacing(3))] z-10"
                aria-label="Hlavní menu"
                data-compact={compact}
            >
                <div className="af-tabbar__pill af-glass">
                    {tabs.map((tab) =>
                        tab.href ? (
                            <Link
                                key={tab.label}
                                href={tab.href}
                                className="af-tabbar__item"
                                aria-current={isCurrent(tab.href, path) ? 'page' : undefined}
                            >
                                <TabLabel icon={tab.icon} label={tab.label} />
                            </Link>
                        ) : (
                            <a
                                key={tab.label}
                                href="#"
                                className="af-tabbar__item"
                                onClick={(event) => event.preventDefault()}
                            >
                                <TabLabel icon={tab.icon} label={tab.label} />
                            </a>
                        ),
                    )}
                    <button
                        type="button"
                        className="af-tabbar__item cursor-pointer"
                        aria-haspopup="dialog"
                        aria-expanded={moreOpen}
                        onClick={() => setMoreOpen(true)}
                    >
                        <TabLabel icon={LayoutGrid} label="Další" />
                    </button>
                </div>

                <Link
                    href="/faktury/nova"
                    className="af-tabbar__fab no-underline"
                    aria-label="Nová faktura"
                >
                    <Plus strokeWidth={2.25} aria-hidden="true" />
                </Link>
            </nav>

            <MoreSheet open={moreOpen} onClose={() => setMoreOpen(false)} />
        </>
    );
}
