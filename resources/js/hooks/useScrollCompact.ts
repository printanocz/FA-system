import { useEffect, useState } from 'react';

/**
 * `true`, když uživatel scrolluje dolů pod horní okraj stránky; při scrollu
 * nahoru nebo na začátku stránky `false` (TabBar se zmenší na ikony a vrátí).
 */
export function useScrollCompact(threshold = 24): boolean {
    const [compact, setCompact] = useState(false);

    useEffect(() => {
        let last = window.scrollY;

        const onScroll = () => {
            const current = window.scrollY;
            if (current <= threshold) {
                setCompact(false);
            } else if (current > last) {
                setCompact(true);
            } else if (current < last) {
                setCompact(false);
            }
            last = current;
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, [threshold]);

    return compact;
}
