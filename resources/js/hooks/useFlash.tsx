import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { useToast } from '@/components/Toast';
import type { FlashError, SharedProps } from '@/lib/shared';

/** Chyba ze serveru buď jako text, nebo jako `{ message, href?, label? }`. */
export function normalizeError(error: SharedProps['error']): FlashError | null {
    if (!error) return null;

    return typeof error === 'string' ? { message: error } : error;
}

/**
 * Hlášky akcí: potvrzení jako toast, chyba jako banner. Hláška po přesměrování se ukáže
 * při načtení stránky; po akci stránky (`router.post …`) ji ukáže `handle` v `onSuccess`,
 * takže se zobrazí i dvakrát za sebou stejná zpráva.
 */
export function useFlash() {
    const { status, error } = usePage<SharedProps>().props;
    const { show, toast } = useToast();
    const [alert, setAlert] = useState<FlashError | null>(normalizeError(error));

    useEffect(() => {
        if (status) show(status);
        // Jen při načtení stránky: pozdější hlášky obslouží `handle`.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handle = useCallback(
        (page: { props: unknown }) => {
            const props = page.props as Partial<SharedProps>;
            if (props.status) show(props.status);
            setAlert(normalizeError(props.error));
        },
        [show],
    );

    const dismiss = useCallback(() => setAlert(null), []);

    return { toast, alert, dismiss, handle, show };
}
