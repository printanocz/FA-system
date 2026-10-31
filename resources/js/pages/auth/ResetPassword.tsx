import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import TextField from '@/components/TextField';
import AuthLayout from '@/layouts/AuthLayout';

type ResetPasswordProps = {
    token: string;
    email: string | null;
};

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email: email ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/reset-password', { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <>
            <Head title="Nové heslo" />
            <h1 className="title-2">Nové heslo</h1>
            <form onSubmit={submit} className="flex flex-col gap-4">
                <TextField
                    label="E-mail"
                    type="email"
                    name="email"
                    autoComplete="username"
                    required
                    value={data.email}
                    onChange={(value) => setData('email', value)}
                    error={errors.email}
                />
                <TextField
                    label="Nové heslo"
                    type="password"
                    name="password"
                    autoComplete="new-password"
                    autoFocus
                    required
                    hint="Alespoň 12 znaků."
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                    error={errors.password}
                />
                <TextField
                    label="Nové heslo znovu"
                    type="password"
                    name="password_confirmation"
                    autoComplete="new-password"
                    required
                    value={data.password_confirmation}
                    onChange={(value) => setData('password_confirmation', value)}
                    error={errors.password_confirmation}
                />
                <button
                    type="submit"
                    className="af-btn af-btn--prominent af-btn--lg w-full"
                    disabled={processing}
                >
                    Uložit heslo
                </button>
                <Link href="/login" className="af-btn af-btn--plain self-center">
                    Zpět na přihlášení
                </Link>
            </form>
        </>
    );
}

ResetPassword.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
