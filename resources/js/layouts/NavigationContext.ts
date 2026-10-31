import { createContext, useContext } from 'react';

type NavigationContextValue = {
    /** Sidebar otevřený jako sheet (jen tablet 760–1023 px). */
    menuOpen: boolean;
    openMenu: () => void;
};

export const NavigationContext = createContext<NavigationContextValue>({
    menuOpen: false,
    openMenu: () => {},
});

export function useNavigation(): NavigationContextValue {
    return useContext(NavigationContext);
}
