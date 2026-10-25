import type { ReactNode } from 'react';
import Sidebar from '@/components/Sidebar';

type AppLayoutProps = {
    children: ReactNode;
};

/**
 * App shell podle artboardu „Přehled — desktop“: plovoucí skleněný Sidebar
 * vlevo, obsah vpravo. Toolbar je součástí stránky (každá obrazovka má vlastní
 * nadpis a akce), proto ho vykresluje stránka, ne layout.
 */
export default function AppLayout({ children }: AppLayoutProps) {
    return (
        <div className="bg-bg text-label font-ui flex min-h-dvh gap-7 p-3">
            <Sidebar className="sticky top-3 hidden h-[calc(100dvh-(--spacing(6)))] shrink-0 self-start lg:flex" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 pt-5 pr-5 pb-10 max-lg:pl-5">
                {children}
            </main>
        </div>
    );
}
