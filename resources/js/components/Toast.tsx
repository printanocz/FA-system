import { useCallback, useEffect, useRef, useState } from 'react';

/** Krátké potvrzení („Uloženo“) plovoucí nad obsahem; zmizí samo po pár vteřinách. */
export function useToast() {
    const [message, setMessage] = useState<string | null>(null);
    const timer = useRef<number | undefined>(undefined);

    const show = useCallback((text: string) => {
        window.clearTimeout(timer.current);
        setMessage(text);
        timer.current = window.setTimeout(() => setMessage(null), 2800);
    }, []);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const toast = (
        <div
            role="status"
            aria-live="polite"
            className="pointer-events-none fixed inset-x-0 bottom-[calc(env(safe-area-inset-bottom)+--spacing(24))] z-30 flex justify-center px-4 desktop:bottom-8 tablet:bottom-8"
        >
            {message && (
                <span className="af-glass text-label rounded-full px-5 py-2.5 text-[14px] leading-5 font-medium">
                    {message}
                </span>
            )}
        </div>
    );

    return { show, toast };
}
