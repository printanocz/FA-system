import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import StatusMessage from '@/components/StatusMessage';
import TextField from '@/components/TextField';
import AuthLayout from '@/layouts/AuthLayout';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/forgot-password');
    };

    return (
        <>
            <Head title="Zapomenuté heslo" />
            <h1 className="title-2">Zapomenuté heslo</h1>
            <p className="body text-label-2">
                Zadejte e-mail a pošleme vám odkaz pro nastavení nového hesla.
            </p>
            <StatusMessage />
            <form onSubmit={submit} className="flex flex-col gap-4">
                <TextField
                    label="E-mail"
                    type="email"
                    name="email"
                    autoComplete="username"
                    autoFocus
                    required
                    value={data.email}
                    onChange={(value) => setData('email', value)}
                    error={errors.email}
                />
                <button
                    type="submit"
                    className="af-btn af-btn--prominent af-btn--lg w-full"
                    disabled={processing}
                >
                    Poslat odkaz
                </button>
                <Link href="/login" className="af-btn af-btn--plain self-center">
                    Zpět na přihlášení
                </Link>
            </form>
        </>
    );
}

ForgotPassword.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
