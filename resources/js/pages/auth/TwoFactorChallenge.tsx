import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import TextField from '@/components/TextField';
import AuthLayout from '@/layouts/AuthLayout';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, transform, reset, clearErrors } = useForm({
        code: '',
        recovery_code: '',
    });

    transform((values) =>
        useRecovery ? { recovery_code: values.recovery_code } : { code: values.code },
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/two-factor-challenge', { onError: () => reset('code', 'recovery_code') });
    };

    const toggle = () => {
        setUseRecovery((current) => !current);
        clearErrors();
        reset('code', 'recovery_code');
    };

    return (
        <>
            <Head title="Ověřovací kód" />
            <h1 className="title-2">Ověřovací kód</h1>
            <p className="body text-label-2">
                {useRecovery
                    ? 'Zadejte jeden ze záložních kódů. Každý jde použít jen jednou.'
                    : 'Zadejte šestimístný kód z autentikační aplikace.'}
            </p>
            <form onSubmit={submit} className="flex flex-col gap-4">
                {useRecovery ? (
                    <TextField
                        key="recovery"
                        label="Záložní kód"
                        name="recovery_code"
                        autoComplete="off"
                        autoFocus
                        required
                        className="font-mono"
                        value={data.recovery_code}
                        onChange={(value) => setData('recovery_code', value)}
                        error={errors.recovery_code ?? errors.code}
                    />
                ) : (
                    <TextField
                        key="code"
                        label="Ověřovací kód"
                        name="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        pattern="[0-9]*"
                        maxLength={6}
                        autoFocus
                        required
                        className="text-center font-mono tracking-widest"
                        value={data.code}
                        onChange={(value) => setData('code', value.replace(/\D/g, ''))}
                        error={errors.code ?? errors.recovery_code}
                    />
                )}
                <button
                    type="submit"
                    className="af-btn af-btn--prominent af-btn--lg w-full"
                    disabled={processing}
                >
                    Ověřit
                </button>
                <button type="button" className="af-btn af-btn--plain self-center" onClick={toggle}>
                    {useRecovery ? 'Použít kód z aplikace' : 'Použít záložní kód'}
                </button>
            </form>
        </>
    );
}

TwoFactorChallenge.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
