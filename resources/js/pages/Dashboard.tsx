import { Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import Toolbar from '@/components/Toolbar';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { MOBILE_QUERY } from '@/lib/breakpoints';
import { formatToday } from '@/lib/date';

type Period = 'month' | 'quarter' | 'year';

/** Přehled: zatím jen prázdný shell, karty a grafy přijdou v T-18. */
export default function Dashboard() {
    const [period, setPeriod] = useState<Period>('year');
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const year = new Date().getFullYear();

    const periods: { value: Period; label: string }[] = [
        { value: 'month', label: 'Měsíc' },
        { value: 'quarter', label: 'Kvartál' },
        { value: 'year', label: isMobile ? 'Rok' : `Rok ${year}` },
    ];

    return (
        <>
            <Head title="Přehled" />
            <Toolbar eyebrow={formatToday(!isMobile)} title="Přehled">
                <div
                    className="af-segmented max-tablet:order-last max-tablet:flex max-tablet:basis-full max-tablet:*:flex-1 max-tablet:*:justify-center"
                    role="group"
                    aria-label="Období"
                >
                    {periods.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            aria-pressed={period === item.value}
                            onClick={() => setPeriod(item.value)}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                <label className="af-search max-tablet:hidden w-60 max-w-full">
                    <Search strokeWidth={1.75} aria-hidden="true" />
                    <input type="search" placeholder="Hledat doklad nebo klienta" />
                    <kbd>⌘K</kbd>
                </label>
                <button
                    type="button"
                    className="af-btn af-btn--glass af-btn--icon af-btn--lg tablet:hidden cursor-pointer"
                    aria-label="Hledat"
                >
                    <Search strokeWidth={1.75} aria-hidden="true" />
                </button>

                {/* Na mobilu nahrazuje tlačítko kulaté „Nová faktura“ v TabBaru (jedna zelená plocha). */}
                <Link
                    className="af-btn af-btn--prominent max-tablet:hidden no-underline"
                    href="/faktury/nova"
                >
                    <Plus strokeWidth={2} aria-hidden="true" />
                    Nová faktura
                </Link>
            </Toolbar>
        </>
    );
}
