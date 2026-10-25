import { Head, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import TextField from '@/components/TextField';
import AuthLayout from '@/layouts/AuthLayout';

export default function Setup({ token }: { token: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/setup', { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <>
            <Head title="První nastavení" />
            <div className="flex flex-col gap-2">
                <h1 className="title-2">První nastavení</h1>
                <p className="body text-label-2 m-0">
                    Vytvořte účet, kterým se budete přihlašovat. Tahle stránka po vytvoření účtu
                    přestane existovat.
                </p>
            </div>
            <form onSubmit={submit} className="flex flex-col gap-4">
                <TextField
                    label="Jméno"
                    name="name"
                    autoComplete="name"
                    autoFocus
                    required
                    value={data.name}
                    onChange={(value) => setData('name', value)}
                    error={errors.name}
                />
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
                    label="Heslo"
                    type="password"
                    name="password"
                    autoComplete="new-password"
                    required
                    hint="Alespoň 12 znaků."
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                    error={errors.password}
                />
                <TextField
                    label="Heslo znovu"
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
                    Vytvořit účet
                </button>
            </form>
        </>
    );
}

Setup.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
