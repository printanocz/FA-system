import { PanelLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useNavigation } from '@/layouts/NavigationContext';
import { MOBILE_QUERY } from '@/lib/breakpoints';

type ToolbarProps = {
    /** Malý řádek nad nadpisem (např. dnešní datum). */
    eyebrow?: string;
    title: string;
    /** Akce vpravo: přepínače, hledání, tlačítka. Každá obrazovka má vlastní. */
    children?: ReactNode;
};

/**
 * Obecný Toolbar obrazovky (`af-toolbar`): nadpis vlevo, akce vpravo. Obsah akcí
 * určuje stránka, komponenta drží rozvržení. Na tabletu (760–1023 px) má vlevo
 * tlačítko, které otevře Sidebar jako sheet; na mobilu je nadpis `large-title`.
 */
export default function Toolbar({ eyebrow, title, children }: ToolbarProps) {
    const { menuOpen, openMenu } = useNavigation();
    const isMobile = useMediaQuery(MOBILE_QUERY);

    return (
        <header className="af-toolbar">
            <button
                type="button"
                className="af-btn af-btn--glass af-btn--icon af-btn--lg tablet:inline-flex desktop:hidden hidden cursor-pointer"
                aria-label="Otevřít menu"
                aria-haspopup="dialog"
                aria-expanded={menuOpen}
                onClick={openMenu}
            >
                <PanelLeft strokeWidth={1.75} aria-hidden="true" />
            </button>

            <div className="mr-auto flex flex-col gap-0.5">
                {eyebrow && (
                    <span className="text-label-3 text-[13px] leading-[18px]">{eyebrow}</span>
                )}
                <h1 className={`${isMobile ? 'large-title' : 'title-1'} m-0`}>{title}</h1>
            </div>
            {children}
        </header>
    );
}
