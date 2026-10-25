import { useState, type ReactNode } from 'react';
import Sheet from '@/components/Sheet';
import Sidebar from '@/components/Sidebar';
import TabBar from '@/components/TabBar';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { MOBILE_QUERY, TABLET_QUERY } from '@/lib/breakpoints';
import { NavigationContext } from '@/layouts/NavigationContext';

type AppLayoutProps = {
    children: ReactNode;
};

/**
 * App shell: plovoucí skleněný Sidebar vlevo na desktopu (≥ 1024 px), na tabletu
 * (760–1023 px) Sidebar jako sheet zleva otevíraný z Toolbaru, na mobilu (< 760 px)
 * TabBar dole. Toolbar vykresluje stránka, protože každá obrazovka má vlastní akce.
 */
export default function AppLayout({ children }: AppLayoutProps) {
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const isTablet = useMediaQuery(TABLET_QUERY);
    const [menuOpen, setMenuOpen] = useState(false);
    const [wasTablet, setWasTablet] = useState(isTablet);

    // Po opuštění tabletu se otevřený sheet zahodí, aby se po návratu sám neotevřel.
    if (wasTablet !== isTablet) {
        setWasTablet(isTablet);
        setMenuOpen(false);
    }

    return (
        <NavigationContext.Provider value={{ menuOpen, openMenu: () => setMenuOpen(true) }}>
            <div className="bg-bg text-label font-ui desktop:p-3 flex min-h-dvh gap-7">
                <Sidebar className="desktop:flex sticky top-3 hidden h-[calc(100dvh-(--spacing(6)))] shrink-0 self-start" />
                <main className="desktop:p-0 desktop:pt-5 desktop:pr-5 desktop:pb-10 tablet:p-8 tablet:pb-10 flex min-w-0 flex-1 flex-col gap-6 px-4 pt-[calc(env(safe-area-inset-top)+--spacing(4))] pb-[calc(env(safe-area-inset-bottom)+--spacing(24))]">
                    {children}
                </main>
            </div>

            {isMobile && <TabBar />}

            {isTablet && (
                <Sheet
                    open={menuOpen}
                    onClose={() => setMenuOpen(false)}
                    side="left"
                    label="Hlavní menu"
                >
                    <Sidebar
                        className="h-full overflow-y-auto"
                        onNavigate={() => setMenuOpen(false)}
                    />
                </Sheet>
            )}
        </NavigationContext.Provider>
    );
}
