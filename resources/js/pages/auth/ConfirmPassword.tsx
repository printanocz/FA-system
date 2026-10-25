import { Head, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import TextField from '@/components/TextField';
import AuthLayout from '@/layouts/AuthLayout';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/user/confirm-password', { onFinish: () => reset('password') });
    };

    return (
        <>
            <Head title="Potvrzení hesla" />
            <h1 className="title-2">Potvrzení hesla</h1>
            <p className="body text-label-2">Tato akce vyžaduje potvrzení vašeho hesla.</p>
            <form onSubmit={submit} className="flex flex-col gap-4">
                <TextField
                    label="Heslo"
                    type="password"
                    name="password"
                    autoComplete="current-password"
                    autoFocus
                    required
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                    error={errors.password}
                />
                <button
                    type="submit"
                    className="af-btn af-btn--prominent af-btn--lg w-full"
                    disabled={processing}
                >
                    Potvrdit
                </button>
            </form>
        </>
    );
}

ConfirmPassword.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
