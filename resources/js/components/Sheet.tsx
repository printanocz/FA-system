import { useEffect, useRef, type ReactNode } from 'react';

type SheetProps = {
    open: boolean;
    onClose: () => void;
    /** `bottom`: sheet zespodu (mobil), `left`: panel zleva (tablet). */
    side: 'bottom' | 'left';
    /** Přístupný název dialogu. */
    label: string;
    children: ReactNode;
};

const panelClasses = {
    bottom: 'absolute inset-x-2 mx-auto w-auto max-w-[560px] bottom-[calc(env(safe-area-inset-bottom)+--spacing(2))] flex max-h-[calc(100dvh-(--spacing(16)))] flex-col transition-transform duration-300 ease-out motion-reduce:transition-none starting:translate-y-full',
    left: 'absolute inset-y-3 left-3 w-62 transition-transform duration-300 ease-out motion-reduce:transition-none starting:-translate-x-full',
} as const;

/**
 * Modální panel nad `scrim` (komponenta Sheet z design systému). Používá nativní
 * `<dialog>`: prohlížeč drží fokus uvnitř, zbytek stránky je neaktivní, Esc zavírá
 * a po zavření se fokus vrátí na prvek, který sheet otevřel. Klik na scrim zavírá.
 */
export default function Sheet({ open, onClose, side, label, children }: SheetProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (open && !dialog.open) {
            dialog.showModal();
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    useEffect(() => {
        if (!open) return;

        const root = document.documentElement;
        const previous = root.style.overflow;
        root.style.overflow = 'hidden';
        return () => {
            root.style.overflow = previous;
        };
    }, [open]);

    return (
        <dialog
            ref={dialogRef}
            aria-label={label}
            onClose={onClose}
            onClick={(event) => {
                if (event.target === event.currentTarget) onClose();
            }}
            className="af-scrim text-label m-0 h-dvh max-h-none w-full max-w-none border-0 p-0 transition-opacity duration-200 motion-reduce:transition-none starting:opacity-0"
        >
            <div className={panelClasses[side]}>{children}</div>
        </dialog>
    );
}
