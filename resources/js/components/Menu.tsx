import { Check } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useLayoutEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { createPortal } from 'react-dom';

export type MenuItem = {
    label: string;
    onSelect?: () => void;
    /** Položka je vidět, ale nejde použít (vedle popisku se ukáže `hint`). */
    disabled?: boolean;
    hint?: string;
    destructive?: boolean;
    /** Vybraná hodnota ve výběrovém menu (zaškrtnutí). */
    selected?: boolean;
    /** Oddělovač nad položkou. */
    separatorBefore?: boolean;
};

type MenuProps = {
    /** Přístupný název menu. */
    label: string;
    items: MenuItem[];
    /** Obsah tlačítka, které menu otevírá. */
    children: ReactNode;
    className?: string;
    /** Odkud se panel zarovná k tlačítku. */
    align?: 'start' | 'end';
};

/**
 * Rozbalovací menu (popover) připojené k tlačítku. Panel se vykresluje do `body`
 * s pevnou polohou, takže ho neořízne `overflow` tabulky. Esc, klik mimo a scroll ho zavřou,
 * šipky přesouvají fokus mezi položkami.
 */
export default function Menu({ label, items, children, className = '', align = 'end' }: MenuProps) {
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState<{ top: number; left: number; maxHeight: number }>();
    const trigger = useRef<HTMLButtonElement>(null);
    const panel = useRef<HTMLDivElement>(null);
    const id = useId();

    const close = useCallback((restoreFocus = true) => {
        setOpen(false);
        if (restoreFocus) trigger.current?.focus();
    }, []);

    useLayoutEffect(() => {
        if (!open || !trigger.current || !panel.current) return;

        const rect = trigger.current.getBoundingClientRect();
        const width = panel.current.offsetWidth;
        const height = panel.current.scrollHeight;
        const gap = 6;
        const below = window.innerHeight - rect.bottom - gap - 8;
        const above = rect.top - gap - 8;
        const flip = height > below && above > below;
        const left = align === 'end' ? rect.right - width : rect.left;

        setPosition({
            left: Math.max(8, Math.min(left, window.innerWidth - width - 8)),
            top: flip ? Math.max(8, rect.top - gap - Math.min(height, above)) : rect.bottom + gap,
            maxHeight: Math.max(160, flip ? above : below),
        });
    }, [open, align]);

    useEffect(() => {
        if (!open) return;

        const onPointerDown = (event: PointerEvent) => {
            const target = event.target as Node;
            if (panel.current?.contains(target) || trigger.current?.contains(target)) return;
            close(false);
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                close();
            }
        };
        const onScroll = (event: Event) => {
            if (panel.current?.contains(event.target as Node)) return;
            close(false);
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKey);
        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', onScroll);

        // Fokus na první použitelnou položku, ať jde menu ovládat klávesnicí.
        panel.current
            ?.querySelector<HTMLElement>('[role^="menuitem"]:not([aria-disabled])')
            ?.focus();

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKey);
            window.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', onScroll);
        };
    }, [open, close]);

    const move = (direction: 1 | -1) => {
        const nodes = Array.from(
            panel.current?.querySelectorAll<HTMLElement>(
                '[role^="menuitem"]:not([aria-disabled])',
            ) ?? [],
        );
        if (nodes.length === 0) return;
        const index = nodes.indexOf(document.activeElement as HTMLElement);
        nodes[(index + direction + nodes.length) % nodes.length].focus();
    };

    return (
        <>
            <button
                ref={trigger}
                type="button"
                className={className}
                aria-label={label}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={open ? id : undefined}
                onClick={(event) => {
                    event.stopPropagation();
                    if (open) {
                        close(false);
                    } else {
                        setPosition(undefined);
                        setOpen(true);
                    }
                }}
            >
                {children}
            </button>

            {open &&
                createPortal(
                    <div
                        ref={panel}
                        id={id}
                        role="menu"
                        aria-label={label}
                        className="af-suggest fixed z-40 min-w-56 overflow-y-auto"
                        style={{
                            top: position?.top ?? 0,
                            left: position?.left ?? 0,
                            maxHeight: position?.maxHeight,
                            visibility: position ? 'visible' : 'hidden',
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'ArrowDown') {
                                event.preventDefault();
                                move(1);
                            } else if (event.key === 'ArrowUp') {
                                event.preventDefault();
                                move(-1);
                            } else if (event.key === 'Tab') {
                                close(false);
                            }
                        }}
                    >
                        {items.map((item) => (
                            <div key={item.label}>
                                {item.separatorBefore && <div className="af-suggest__sep" />}
                                <button
                                    type="button"
                                    role={
                                        item.selected === undefined ? 'menuitem' : 'menuitemradio'
                                    }
                                    aria-checked={item.selected}
                                    aria-disabled={item.disabled || undefined}
                                    title={item.disabled ? item.hint : undefined}
                                    className={`flex w-full items-center gap-2 rounded-[8px] border-0 bg-transparent px-2.5 py-2 text-left text-[14px] leading-5 ${
                                        item.disabled
                                            ? 'text-label-3 cursor-default'
                                            : `hover:bg-fill focus-visible:bg-fill cursor-pointer outline-none ${
                                                  item.destructive ? 'text-red' : 'text-label'
                                              }`
                                    }`}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        if (item.disabled) return;
                                        close(false);
                                        item.onSelect?.();
                                    }}
                                >
                                    <span className="flex-1 truncate">{item.label}</span>
                                    {item.disabled && item.hint && (
                                        <span className="text-label-3 text-xs">{item.hint}</span>
                                    )}
                                    {item.selected && (
                                        <Check
                                            className="text-accent-text size-4"
                                            strokeWidth={2}
                                            aria-hidden="true"
                                        />
                                    )}
                                </button>
                            </div>
                        ))}
                    </div>,
                    document.body,
                )}
        </>
    );
}
