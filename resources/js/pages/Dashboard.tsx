import { Head } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import Toolbar from '@/components/Toolbar';
import { formatToday } from '@/lib/date';

type Period = 'month' | 'quarter' | 'year';

/** Přehled: zatím jen prázdný shell, karty a grafy přijdou v T-18. */
export default function Dashboard() {
    const [period, setPeriod] = useState<Period>('year');
    const year = new Date().getFullYear();

    const periods: { value: Period; label: string }[] = [
        { value: 'month', label: 'Měsíc' },
        { value: 'quarter', label: 'Kvartál' },
        { value: 'year', label: `Rok ${year}` },
    ];

    return (
        <>
            <Head title="Přehled" />
            <Toolbar eyebrow={formatToday()} title="Přehled">
                <div className="af-segmented" role="group" aria-label="Období">
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

                <label className="af-search w-60 max-w-full">
                    <Search strokeWidth={1.75} aria-hidden="true" />
                    <input type="search" placeholder="Hledat doklad nebo klienta" />
                    <kbd>⌘K</kbd>
                </label>

                <a
                    className="af-btn af-btn--prominent no-underline"
                    href="#"
                    onClick={(event) => event.preventDefault()}
                >
                    <Plus strokeWidth={2} aria-hidden="true" />
                    Nová faktura
                </a>
            </Toolbar>
        </>
    );
}
