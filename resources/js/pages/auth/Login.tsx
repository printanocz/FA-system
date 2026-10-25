import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import StatusMessage from '@/components/StatusMessage';
import TextField from '@/components/TextField';
import AuthLayout from '@/layouts/AuthLayout';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <>
            <Head title="Přihlášení" />
            <h1 className="title-2">Přihlášení</h1>
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
                <TextField
                    label="Heslo"
                    type="password"
                    name="password"
                    autoComplete="current-password"
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
                    Přihlásit se
                </button>
                <Link href="/forgot-password" className="af-btn af-btn--plain self-center">
                    Zapomenuté heslo
                </Link>
            </form>
        </>
    );
}

Login.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
