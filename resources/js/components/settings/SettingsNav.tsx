import { ChevronRight } from 'lucide-react';
import { sections, type SectionId } from '@/lib/settings';

/** Vnitřní navigace sekcí na desktopu a tabletu: sticky sloupec odkazů s kotvami. */
export function SideNav({ current }: { current: SectionId }) {
    return (
        <nav
            className="set-nav sticky top-5 w-[200px] shrink-0 self-start"
            aria-label="Sekce nastavení"
        >
            {sections.map((section) =>
                section.soon ? (
                    <span
                        key={section.id}
                        className="set-nav__item text-label-3 flex h-[34px] cursor-default items-center justify-between gap-2 rounded-[10px] px-3 text-[14px]"
                        aria-disabled="true"
                    >
                        {section.label}
                        <span className="text-[11px]">Připravujeme</span>
                    </span>
                ) : (
                    <a
                        key={section.id}
                        href={`#${section.id}`}
                        aria-current={current === section.id ? 'true' : undefined}
                    >
                        {section.label}
                    </a>
                ),
            )}
        </nav>
    );
}

/** Na mobilu je navigace seskupený seznam; řádek otevře podstránku sekce (`#sekce`). */
export function GroupedNav() {
    return (
        <nav aria-label="Sekce nastavení" className="af-list">
            {sections.map((section) =>
                section.soon ? (
                    <div
                        key={section.id}
                        className="af-list__row text-label-3"
                        aria-disabled="true"
                    >
                        <span className="af-list__label">{section.label}</span>
                        <span className="text-[13px]">Připravujeme</span>
                    </div>
                ) : (
                    <a
                        key={section.id}
                        href={`#${section.id}`}
                        className="af-list__row text-label no-underline"
                    >
                        <span className="af-list__label">{section.label}</span>
                        <ChevronRight
                            className="af-list__chevron"
                            strokeWidth={2}
                            aria-hidden="true"
                        />
                    </a>
                ),
            )}
        </nav>
    );
}
