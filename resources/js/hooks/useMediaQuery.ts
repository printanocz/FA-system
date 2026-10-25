import { useSyncExternalStore } from 'react';

/** Reaktivní `matchMedia`. Aplikace se vykresluje jen v prohlížeči, takže SSR hodnota není potřeba. */
export function useMediaQuery(query: string): boolean {
    return useSyncExternalStore(
        (notify) => {
            const list = window.matchMedia(query);
            list.addEventListener('change', notify);
            return () => list.removeEventListener('change', notify);
        },
        () => window.matchMedia(query).matches,
        () => false,
    );
}
