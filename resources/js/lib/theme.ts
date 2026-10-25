export type ThemePreference = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'af-theme';
const DARK_QUERY = '(prefers-color-scheme: dark)';

/** Uložená volba vzhledu. Bez uložené volby je výchozí světlý režim. */
export function getThemePreference(): ThemePreference {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored === 'dark' || stored === 'system' ? stored : 'light';
    } catch {
        return 'light';
    }
}

function resolve(preference: ThemePreference): 'light' | 'dark' {
    if (preference === 'system') {
        return window.matchMedia(DARK_QUERY).matches ? 'dark' : 'light';
    }
    return preference;
}

export function applyTheme(preference: ThemePreference): void {
    document.documentElement.dataset.theme = resolve(preference);
}

export function setThemePreference(preference: ThemePreference): void {
    try {
        localStorage.setItem(STORAGE_KEY, preference);
    } catch {
        // Úložiště nemusí být dostupné (soukromé okno); volba platí do znovunačtení.
    }
    applyTheme(preference);
}

/** U volby „Podle systému“ sleduje změnu systémového režimu. Volá se jednou při startu. */
export function watchSystemTheme(): void {
    window.matchMedia(DARK_QUERY).addEventListener('change', () => {
        if (getThemePreference() === 'system') applyTheme('system');
    });
}
