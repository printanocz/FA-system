import { Plus, Search } from 'lucide-react';
import { useState } from 'react';

type Period = 'month' | 'quarter' | 'year';

function formatToday(): string {
    const text = new Intl.DateTimeFormat('cs-CZ', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    return text.charAt(0).toUpperCase() + text.slice(1);
}

/**
 * Toolbar obrazovky Přehled podle plátna: datum a nadpis vlevo, přepínač
 * období, vyhledávání a prominentní akce „Nová faktura“ vpravo.
 */
export default function Toolbar({ title }: { title: string }) {
    const [period, setPeriod] = useState<Period>('year');
    const year = new Date().getFullYear();

    const periods: { value: Period; label: string }[] = [
        { value: 'month', label: 'Měsíc' },
        { value: 'quarter', label: 'Kvartál' },
        { value: 'year', label: `Rok ${year}` },
    ];

    return (
        <header className="af-toolbar">
            <div className="mr-auto flex flex-col gap-0.5">
                <span className="text-label-3 text-[13px] leading-[18px]">{formatToday()}</span>
                <h1 className="title-1 m-0">{title}</h1>
            </div>

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
        </header>
    );
}
