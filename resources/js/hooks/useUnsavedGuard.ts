import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type PendingNavigation = {
    url: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

/**
 * Hlídá odchod z formuláře s neuloženými změnami. Zastaví navigaci na jinou stránku
 * (včetně odhlášení) a nechá zobrazit potvrzení; ukládání formuláře a Sheety, které
 * míří na jiné URL jen jako POST/PUT/DELETE, nechává projít. Zavření záložky či
 * obnovení řeší `beforeunload`.
 */
export function useUnsavedGuard(dirty: boolean) {
    const [pending, setPending] = useState<PendingNavigation | null>(null);
    const bypass = useRef(false);

    useEffect(() => {
        if (!dirty) return;

        return router.on('before', (event) => {
            if (bypass.current) return;

            const { url, method } = event.detail.visit;
            const leaves =
                method === 'get'
                    ? url.pathname !== window.location.pathname
                    : url.pathname === '/logout';
            if (!leaves) return;

            setPending({ url: url.pathname + url.search + url.hash, method });
            return false;
        });
    }, [dirty]);

    useEffect(() => {
        if (!dirty) return;

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    const stay = useCallback(() => setPending(null), []);

    const leave = useCallback(() => {
        if (!pending) return;

        bypass.current = true;
        setPending(null);
        router.visit(pending.url, { method: pending.method });
        bypass.current = false;
    }, [pending]);

    return { pending: pending !== null, stay, leave };
}
