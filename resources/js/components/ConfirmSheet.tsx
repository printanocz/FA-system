import Sheet from '@/components/Sheet';

type ConfirmSheetProps = {
    open: boolean;
    title: string;
    text: string;
    confirmLabel: string;
    cancelLabel?: string;
    /** Potvrzení je nevratné (červené tlačítko). */
    destructive?: boolean;
    busy?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
};

/** Potvrzovací Sheet zespodu: nadpis, vysvětlení a dvě tlačítka. */
export default function ConfirmSheet({
    open,
    title,
    text,
    confirmLabel,
    cancelLabel = 'Zrušit',
    destructive = false,
    busy = false,
    onConfirm,
    onCancel,
}: ConfirmSheetProps) {
    return (
        <Sheet open={open} onClose={onCancel} side="bottom" label={title}>
            <div className="af-sheet">
                <div className="af-sheet__grabber" aria-hidden="true" />
                <h2 className="af-sheet__title">{title}</h2>
                <p className="af-sheet__text">{text}</p>
                <div className="af-sheet__actions">
                    <button type="button" className="af-btn" onClick={onCancel}>
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        className={`af-btn ${destructive ? 'af-btn--destructive' : 'af-btn--prominent'}`}
                        disabled={busy}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </Sheet>
    );
}
