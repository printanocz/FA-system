import { Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { SectionTitle } from '@/components/settings/SettingsRow';
import Sheet from '@/components/Sheet';
import TextField from '@/components/TextField';
import type { AccountData } from '@/lib/settings';
import { getThemePreference, setThemePreference, type ThemePreference } from '@/lib/theme';

const themes: { value: ThemePreference; label: string }[] = [
    { value: 'light', label: 'Světlý' },
    { value: 'dark', label: 'Tmavý' },
    { value: 'system', label: 'Podle systému' },
];

function PasswordForm({
    onClose,
    onSaved,
}: {
    onClose: () => void;
    onSaved: (message: string) => void;
}) {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put('/user/password', {
            errorBag: 'updatePassword',
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reset();
                onClose();
                onSaved('Heslo změněno');
            },
        });
    };

    return (
        <form onSubmit={submit} className="af-sheet min-h-0 overflow-y-auto overscroll-contain">
            <div className="af-sheet__grabber" aria-hidden="true" />
            <h2 className="af-sheet__title">Změnit heslo</h2>
            <TextField
                label="Současné heslo"
                type="password"
                autoComplete="current-password"
                value={data.current_password}
                onChange={(value) => setData('current_password', value)}
                error={errors.current_password}
                required
            />
            <TextField
                label="Nové heslo"
                type="password"
                autoComplete="new-password"
                hint="Nejméně 12 znaků."
                value={data.password}
                onChange={(value) => setData('password', value)}
                error={errors.password}
                required
            />
            <TextField
                label="Nové heslo znovu"
                type="password"
                autoComplete="new-password"
                value={data.password_confirmation}
                onChange={(value) => setData('password_confirmation', value)}
                error={errors.password_confirmation}
                required
            />
            <div className="af-sheet__actions">
                <button type="button" className="af-btn" onClick={onClose}>
                    Zrušit
                </button>
                <button type="submit" className="af-btn af-btn--prominent" disabled={processing}>
                    Změnit heslo
                </button>
            </div>
        </form>
    );
}

export default function AccountSection({
    account,
    onSaved,
}: {
    account: AccountData;
    onSaved: (message: string) => void;
}) {
    const [theme, setTheme] = useState<ThemePreference>(getThemePreference);
    const [passwordOpen, setPasswordOpen] = useState(false);

    return (
        <section id="ucet" aria-labelledby="h-ucet" className="scroll-mt-5">
            <SectionTitle id="h-ucet">Účet a zabezpečení</SectionTitle>
            <div className="af-list">
                <div className="af-list__row">
                    <span className="af-list__label">Přihlašovací e-mail</span>
                    <span className="af-list__value break-all">{account.email}</span>
                </div>
                <div className="af-list__row">
                    <span className="af-list__label">Heslo</span>
                    <button
                        type="button"
                        className="af-btn af-btn--plain af-btn--sm"
                        onClick={() => setPasswordOpen(true)}
                    >
                        Změnit heslo
                    </button>
                </div>
                <div className="af-list__row">
                    <span className="af-list__label">Dvoufázové ověření</span>
                    {account.twoFactor ? (
                        <>
                            <span className="af-badge af-badge--green">Zapnuto</span>
                            <Link
                                href="/nastaveni/zalozni-kody"
                                className="af-btn af-btn--plain af-btn--sm"
                            >
                                Záložní kódy
                            </Link>
                        </>
                    ) : (
                        <span className="af-badge af-badge--orange">Vypnuto</span>
                    )}
                </div>
                <div className="af-list__row">
                    <span className="af-list__label">Tmavý režim</span>
                    <div className="af-segmented" role="group" aria-label="Vzhled">
                        {themes.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                aria-pressed={theme === item.value}
                                onClick={() => {
                                    setTheme(item.value);
                                    setThemePreference(item.value);
                                }}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="af-list__row">
                    <button
                        type="button"
                        className="af-btn af-btn--plain text-red p-0"
                        onClick={() => router.post('/logout')}
                    >
                        Odhlásit se
                    </button>
                </div>
            </div>

            <Sheet
                open={passwordOpen}
                onClose={() => setPasswordOpen(false)}
                side="bottom"
                label="Změnit heslo"
            >
                {passwordOpen && (
                    <PasswordForm onClose={() => setPasswordOpen(false)} onSaved={onSaved} />
                )}
            </Sheet>
        </section>
    );
}
