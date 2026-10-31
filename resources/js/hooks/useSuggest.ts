import { useEffect, useRef, useState } from 'react';

const DELAY_MS = 200;

/**
 * Našeptávač nad JSON endpointem (`GET url?q=…`, odpověď `{ [key]: T[] }`). Hledá po krátké
 * pauze v psaní a zahazuje zpožděné odpovědi, které už neodpovídají poslednímu dotazu.
 */
export function useSuggest<T>(url: string, key: string, query: string, enabled: boolean): T[] {
    const [items, setItems] = useState<T[]>([]);
    const latest = useRef(0);

    useEffect(() => {
        if (!enabled) return;

        const id = ++latest.current;
        const timer = window.setTimeout(() => {
            fetch(`${url}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((json: Record<string, T[]> | null) => {
                    if (json && id === latest.current) setItems(json[key] ?? []);
                })
                .catch(() => undefined);
        }, DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [url, key, query, enabled]);

    return enabled ? items : [];
}
