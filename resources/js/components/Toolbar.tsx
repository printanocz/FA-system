import type { ReactNode } from 'react';

type ToolbarProps = {
    /** Malý řádek nad nadpisem (např. dnešní datum). */
    eyebrow?: string;
    title: string;
    /** Akce vpravo: přepínače, hledání, tlačítka. Každá obrazovka má vlastní. */
    children?: ReactNode;
};

/**
 * Obecný Toolbar obrazovky (`af-toolbar`): nadpis vlevo, akce vpravo.
 * Obsah akcí určuje stránka, komponenta jen drží rozvržení.
 */
export default function Toolbar({ eyebrow, title, children }: ToolbarProps) {
    return (
        <header className="af-toolbar">
            <div className="mr-auto flex flex-col gap-0.5">
                {eyebrow && (
                    <span className="text-label-3 text-[13px] leading-[18px]">{eyebrow}</span>
                )}
                <h1 className="title-1 m-0">{title}</h1>
            </div>
            {children}
        </header>
    );
}
