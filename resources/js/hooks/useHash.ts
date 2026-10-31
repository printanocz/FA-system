import { router, usePage } from '@inertiajs/react';
import { useEffect, useSyncExternalStore } from 'react';

/** Hash připnutý po dobu odeslání formuláře, aby sekce během návštěvy nezmizela. */
let pinned = '';

function subscribe(notify: () => void) {
    window.addEventListener('hashchange', notify);
    return () => window.removeEventListener('hashchange', notify);
}

/**
 * Aktuální hash adresy bez `#`. Změnu přes odkaz `#sekce` hlásí `hashchange`,
 * změnu přes Inertia návštěvu (`/nastaveni#ucet`) přináší nová `url` stránky.
 *
 * Odeslání formuláře (PUT/POST/DELETE) hash zahazuje, protože přesměrování vrací
 * adresu bez něj. Po takové návštěvě ho proto vrátíme, aby mobilní podstránka
 * sekce po uložení nebo chybě validace zůstala otevřená.
 */
export function useHash(): string {
    usePage();

    useEffect(() => {
        const offBefore = router.on('before', (event) => {
            pinned = event.detail.visit.method !== 'get' ? window.location.hash : '';
        });
        const offFinish = router.on('finish', () => {
            if (pinned && !window.location.hash) {
                window.history.replaceState(
                    window.history.state,
                    '',
                    `${window.location.pathname}${window.location.search}${pinned}`,
                );
            }
            pinned = '';
            window.dispatchEvent(new HashChangeEvent('hashchange'));
        });

        return () => {
            offBefore();
            offFinish();
            pinned = '';
        };
    }, []);

    return useSyncExternalStore(
        subscribe,
        () => (pinned || window.location.hash).slice(1),
        () => '',
    );
}
