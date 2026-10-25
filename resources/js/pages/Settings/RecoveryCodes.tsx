import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import Toolbar from '@/components/Toolbar';

export default function RecoveryCodes({ recoveryCodes }: { recoveryCodes: string[] }) {
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
            <Head title="Záložní kódy" />
            <Toolbar title="Záložní kódy" />

            <div className="flex max-w-[560px] flex-col gap-4">
                <Link
                    href="/nastaveni#ucet"
                    className="text-accent-text inline-flex items-center gap-1 text-[15px] no-underline"
                >
                    <ChevronLeft className="size-4" strokeWidth={2} aria-hidden="true" />
                    Nastavení · Účet a zabezpečení
                </Link>
                <p className="body text-label-2 m-0">
                    Každý kód jde použít jen jednou. Uložte si je na bezpečné místo pro případ, že
                    ztratíte telefon s autentikační aplikací.
                </p>
                <div className="af-list">
                    {recoveryCodes.map((code) => (
                        <div key={code} className="af-list__row af-mono">
                            {code}
                        </div>
                    ))}
                </div>
                <div className="flex flex-wrap gap-3">
                    <button type="button" className="af-btn" onClick={copy}>
                        {copied ? 'Zkopírováno' : 'Zkopírovat'}
                    </button>
                    <button
                        type="button"
                        className="af-btn af-btn--destructive"
                        onClick={() =>
                            router.post(
                                '/user/two-factor-recovery-codes',
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Vygenerovat nové kódy
                    </button>
                </div>
            </div>
        </>
    );
}
