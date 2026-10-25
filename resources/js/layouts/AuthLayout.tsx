import type { ReactNode } from 'react';

type AuthLayoutProps = {
    children: ReactNode;
};

/**
 * Layout přihlašovacích obrazovek: bez Sidebaru, karta max. 400 px uprostřed na `bg`,
 * nahoře značka AF a název aplikace.
 */
export default function AuthLayout({ children }: AuthLayoutProps) {
    return (
        <div className="bg-bg text-label font-ui flex min-h-dvh items-center justify-center px-4 py-10">
            <main className="af-card flex w-full max-w-[400px] flex-col gap-6">
                <div className="af-sidebar__brand justify-center p-0">
                    <span className="af-sidebar__mark">AF</span>
                    Fakturace
                </div>
                {children}
            </main>
        </div>
    );
}
