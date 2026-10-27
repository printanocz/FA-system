import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Sheet from '@/components/Sheet';
import TextField from '@/components/TextField';
import { formatMoney } from '@/lib/format';
import { czToIso, formatDecimal, isoToCz } from '@/lib/invoice';
import { PAYMENT_METHODS, type PaymentMethodValue } from '@/lib/invoices';

type PaymentSheetProps = {
    open: boolean;
    invoiceId: number;
    /** Zbývající částka (řetězec s tečkou): předvyplní se jako výchozí úhrada. */
    remaining: string;
    currency: string;
    onClose: () => void;
    onDone: (page: { props: unknown }) => void;
};

function todayIso(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function PaymentForm({
    invoiceId,
    remaining,
    currency,
    onClose,
    onDone,
}: Omit<PaymentSheetProps, 'open'>) {
    const f = useForm<{
        amount: string;
        paid_at: string;
        method: PaymentMethodValue;
        note: string;
    }>({
        amount: formatDecimal(remaining),
        paid_at: isoToCz(todayIso()),
        method: 'bank_transfer',
        note: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        f.transform((data) => ({ ...data, paid_at: czToIso(data.paid_at) ?? data.paid_at }));
        f.post(`/faktury/${invoiceId}/uhrady`, {
            preserveScroll: true,
            onSuccess: (page) => {
                onDone(page);
                onClose();
            },
        });
    };

    return (
        <form onSubmit={submit} className="af-sheet min-h-0 overflow-y-auto overscroll-contain">
            <div className="af-sheet__grabber" aria-hidden="true" />
            <h2 className="af-sheet__title">Přidat úhradu</h2>
            <p className="af-sheet__text">
                Zbývá uhradit {formatMoney(remaining, currency)}. Částečná úhrada je v pořádku,
                přeplatek ne.
            </p>

            <TextField
                label="Částka"
                value={f.data.amount}
                onChange={(value) => f.setData('amount', value)}
                error={f.errors.amount}
                inputMode="decimal"
                autoComplete="off"
                autoFocus
                required
            />
            <TextField
                label="Datum úhrady"
                value={f.data.paid_at}
                onChange={(value) => f.setData('paid_at', value)}
                error={f.errors.paid_at}
                autoComplete="off"
                required
            />

            <div className="af-field">
                <span className="af-field__label" id="pay-method-label">
                    Způsob
                </span>
                <div
                    className="af-segmented self-start"
                    role="group"
                    aria-labelledby="pay-method-label"
                >
                    {PAYMENT_METHODS.map((method) => (
                        <button
                            key={method.value}
                            type="button"
                            aria-pressed={f.data.method === method.value}
                            onClick={() => f.setData('method', method.value)}
                        >
                            {method.label}
                        </button>
                    ))}
                </div>
                {f.errors.method && <span className="af-field__error">{f.errors.method}</span>}
            </div>

            <TextField
                label="Poznámka"
                value={f.data.note}
                onChange={(value) => f.setData('note', value)}
                error={f.errors.note}
                autoComplete="off"
                maxLength={255}
            />

            <div className="af-sheet__actions">
                <button type="button" className="af-btn cursor-pointer" onClick={onClose}>
                    Zrušit
                </button>
                <button
                    type="submit"
                    className="af-btn af-btn--prominent cursor-pointer"
                    disabled={f.processing}
                >
                    Přidat úhradu
                </button>
            </div>
        </form>
    );
}

/** Sheet pro ruční úhradu: částka (předvyplněná zbývající), datum (dnes), způsob a poznámka. */
export default function PaymentSheet({ open, ...rest }: PaymentSheetProps) {
    return (
        <Sheet open={open} onClose={rest.onClose} side="bottom" label="Přidat úhradu">
            {open && <PaymentForm {...rest} />}
        </Sheet>
    );
}
