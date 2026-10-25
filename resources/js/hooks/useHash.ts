import { usePage } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

function subscribe(notify: () => void) {
    window.addEventListener('hashchange', notify);
    return () => window.removeEventListener('hashchange', notify);
}

/**
 * Aktuální hash adresy bez `#`. Změnu přes odkaz `#sekce` hlásí `hashchange`,
 * změnu přes Inertia návštěvu (`/nastaveni#ucet`) přináší nová `url` stránky.
 */
export function useHash(): string {
    usePage();
    return useSyncExternalStore(
        subscribe,
        () => window.location.hash.slice(1),
        () => '',
    );
}
