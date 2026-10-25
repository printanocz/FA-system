import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import TextField from '@/components/TextField';
import AuthLayout from '@/layouts/AuthLayout';

type TwoFactorSetupProps = {
    step: 'start' | 'confirm' | 'codes';
    qrCodeSvg: string | null;
    secretKey: string | null;
    recoveryCodes: string[];
};

function StartStep() {
    const { post, processing } = useForm({});

    return (
        <>
            <h1 className="title-2">Dvoufázové ověření</h1>
            <p className="body text-label-2">
                Pro přístup do aplikace je dvoufázové ověření povinné. Budete potřebovat
                autentikační aplikaci (například Google Authenticator, 1Password nebo Authy).
            </p>
            <button
                type="button"
                className="af-btn af-btn--prominent af-btn--lg w-full"
                disabled={processing}
                onClick={() => post('/user/two-factor-authentication')}
            >
                Začít nastavení
            </button>
        </>
    );
}

function ConfirmStep({
    qrCodeSvg,
    secretKey,
}: Pick<TwoFactorSetupProps, 'qrCodeSvg' | 'secretKey'>) {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/user/confirmed-two-factor-authentication', {
            errorBag: 'confirmTwoFactorAuthentication',
        });
    };

    return (
        <>
            <h1 className="title-2">Zapnutí dvoufázového ověření</h1>

            <section className="flex flex-col gap-3">
                <h2 className="af-card__title">Krok 1: naskenujte QR kód</h2>
                {qrCodeSvg && (
                    <div
                        className="mx-auto size-[192px]"
                        role="img"
                        aria-label="QR kód pro autentikační aplikaci"
                        dangerouslySetInnerHTML={{ __html: qrCodeSvg }}
                    />
                )}
                {secretKey && (
                    <div className="flex flex-col gap-1">
                        <span className="footnote text-label-3">Nebo zadejte klíč ručně</span>
                        <code className="af-mono break-all">{secretKey}</code>
                    </div>
                )}
            </section>

            <form onSubmit={submit} className="flex flex-col gap-4">
                <h2 className="af-card__title">Krok 2: opište kód z aplikace</h2>
                <TextField
                    label="Ověřovací kód"
                    name="code"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    pattern="[0-9]*"
                    maxLength={6}
                    required
                    className="text-center font-mono tracking-widest"
                    value={data.code}
                    onChange={(value) => setData('code', value.replace(/\D/g, ''))}
                    error={errors.code}
                />
                <button
                    type="submit"
                    className="af-btn af-btn--prominent af-btn--lg w-full"
                    disabled={processing}
                >
                    Zapnout
                </button>
            </form>
        </>
    );
}

function CodesStep({ recoveryCodes }: Pick<TwoFactorSetupProps, 'recoveryCodes'>) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(recoveryCodes.join('\n'));
            setCopied(true);
        } catch {
            setCopied(false);
        }
    };

    return (
        <>
            <h1 className="title-2">Záložní kódy</h1>
            <p className="body text-label-2">
                Dvoufázové ověření je zapnuté. Uložte si záložní kódy na bezpečné místo, pro případ
                že ztratíte telefon. Každý kód jde použít jen jednou.
            </p>
            <div className="af-list bg-surface-2">
                {recoveryCodes.map((code) => (
                    <div key={code} className="af-list__row af-mono">
                        {code}
                    </div>
                ))}
            </div>
            <div className="flex flex-col gap-3">
                <button type="button" className="af-btn af-btn--lg w-full" onClick={copy}>
                    {copied ? 'Zkopírováno' : 'Zkopírovat'}
                </button>
                <Link href="/" className="af-btn af-btn--prominent af-btn--lg w-full">
                    Pokračovat
                </Link>
            </div>
        </>
    );
}

export default function TwoFactorSetup({
    step,
    qrCodeSvg,
    secretKey,
    recoveryCodes,
}: TwoFactorSetupProps) {
    return (
        <>
            <Head title="Dvoufázové ověření" />
            {step === 'start' && <StartStep />}
            {step === 'confirm' && <ConfirmStep qrCodeSvg={qrCodeSvg} secretKey={secretKey} />}
            {step === 'codes' && <CodesStep recoveryCodes={recoveryCodes} />}
        </>
    );
}

TwoFactorSetup.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
