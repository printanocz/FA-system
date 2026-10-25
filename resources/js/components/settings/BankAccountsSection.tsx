import { router, useForm } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import ConfirmSheet from '@/components/ConfirmSheet';
import { Toggle } from '@/components/settings/SettingsRow';
import Sheet from '@/components/Sheet';
import TextField from '@/components/TextField';
import { czechIban, prettyIban } from '@/lib/iban';
import type { BankAccountData } from '@/lib/settings';

type BankAccountsSectionProps = {
    accounts: BankAccountData[];
    onSaved: (message: string) => void;
};

function AccountForm({
    account,
    onClose,
    onSaved,
}: {
    account: BankAccountData | null;
    onClose: () => void;
    onSaved: (message: string) => void;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: account?.name ?? '',
        account_number: account?.account_number ?? '',
        bank_code: account?.bank_code ?? '',
        swift: account?.swift ?? '',
        is_default: account?.is_default ?? false,
    });
    const [confirmDelete, setConfirmDelete] = useState(false);

    const iban = czechIban(data.account_number, data.bank_code);
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            onClose();
            onSaved('Uloženo');
        },
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (account) put(`/nastaveni/ucty/${account.id}`, options);
        else post('/nastaveni/ucty', options);
    };

    const remove = () =>
        router.delete(`/nastaveni/ucty/${account?.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setConfirmDelete(false);
                onClose();
                onSaved('Účet smazán');
            },
        });

    return (
        <form onSubmit={submit} className="af-sheet min-h-0 overflow-y-auto overscroll-contain">
            <div className="af-sheet__grabber" aria-hidden="true" />
            <h2 className="af-sheet__title">{account ? 'Upravit účet' : 'Přidat účet'}</h2>

            <TextField
                label="Název"
                value={data.name}
                onChange={(value) => setData('name', value)}
                error={errors.name}
                placeholder="Česká spořitelna · podnikání"
                required
            />
            <div className="grid grid-cols-[minmax(0,1fr)_96px] gap-3">
                <TextField
                    label="Číslo účtu"
                    value={data.account_number}
                    onChange={(value) => setData('account_number', value)}
                    error={errors.account_number}
                    inputMode="numeric"
                    className="af-mono"
                    placeholder="19-2000145399"
                    required
                />
                <TextField
                    label="Kód banky"
                    value={data.bank_code}
                    onChange={(value) => setData('bank_code', value.replace(/\D/g, '').slice(0, 4))}
                    error={errors.bank_code}
                    inputMode="numeric"
                    className="af-mono"
                    placeholder="0800"
                    required
                />
            </div>
            <div className="af-field">
                <span className="af-field__label">IBAN</span>
                <div className="af-mono text-label min-h-6 text-[15px]" aria-live="polite">
                    {iban ? (
                        prettyIban(iban)
                    ) : (
                        <span className="text-label-3">Dopočítá se z čísla účtu a kódu banky</span>
                    )}
                </div>
            </div>
            <TextField
                label="SWIFT (volitelné)"
                value={data.swift}
                onChange={(value) => setData('swift', value.toUpperCase())}
                error={errors.swift}
                className="af-mono"
                placeholder="GIBACZPX"
            />
            <div className="flex items-center justify-between gap-3">
                <span>Výchozí účet</span>
                <Toggle
                    checked={data.is_default}
                    label="Výchozí účet"
                    onChange={(checked) => setData('is_default', checked)}
                />
            </div>

            <div className="af-sheet__actions">
                {account && (
                    <button
                        type="button"
                        className="af-btn af-btn--destructive mr-auto"
                        onClick={() => setConfirmDelete(true)}
                    >
                        Smazat
                    </button>
                )}
                <button type="button" className="af-btn" onClick={onClose}>
                    Zrušit
                </button>
                <button type="submit" className="af-btn af-btn--prominent" disabled={processing}>
                    Uložit
                </button>
            </div>

            <ConfirmSheet
                open={confirmDelete}
                title="Smazat účet?"
                text="Vystavené doklady si účet ponechají, ale nové doklady ho už nenabídnou."
                confirmLabel="Smazat účet"
                destructive
                onConfirm={remove}
                onCancel={() => setConfirmDelete(false)}
            />
        </form>
    );
}

export default function BankAccountsSection({ accounts, onSaved }: BankAccountsSectionProps) {
    const [editing, setEditing] = useState<BankAccountData | 'new' | null>(null);
    const close = () => setEditing(null);

    return (
        <section id="ucty" aria-labelledby="h-ucty" className="scroll-mt-5">
            <div className="flex items-baseline justify-between px-4 pb-2">
                <h2 id="h-ucty" className="m-0 text-[15px] leading-5 font-semibold">
                    Bankovní účty
                </h2>
                <button
                    type="button"
                    className="af-btn af-btn--plain af-btn--sm"
                    onClick={() => setEditing('new')}
                >
                    Přidat účet
                </button>
            </div>
            <div className="af-list">
                {accounts.length === 0 && (
                    <div className="af-list__row text-label-3">
                        Zatím žádný účet. Přidejte první.
                    </div>
                )}
                {accounts.map((account) => (
                    <button
                        key={account.id}
                        type="button"
                        className="af-list__row w-full cursor-pointer border-0 bg-transparent text-left font-[inherit] text-[length:inherit]"
                        onClick={() => setEditing(account)}
                    >
                        <div className="af-list__label">
                            <div className="text-label font-semibold">{account.name}</div>
                            <div className="af-mono text-label-3 text-xs break-all">
                                {account.account_number}/{account.bank_code} ·{' '}
                                {prettyIban(account.iban)}
                            </div>
                        </div>
                        {account.is_default && (
                            <span className="af-badge af-badge--green">Výchozí</span>
                        )}
                        <ChevronRight
                            className="af-list__chevron"
                            strokeWidth={2}
                            aria-hidden="true"
                        />
                    </button>
                ))}
            </div>

            <Sheet open={editing !== null} onClose={close} side="bottom" label="Bankovní účet">
                {editing !== null && (
                    <AccountForm
                        key={editing === 'new' ? 'new' : editing.id}
                        account={editing === 'new' ? null : editing}
                        onClose={close}
                        onSaved={onSaved}
                    />
                )}
            </Sheet>
        </section>
    );
}
