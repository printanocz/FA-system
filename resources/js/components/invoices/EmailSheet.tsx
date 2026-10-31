import { useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import type { FormEvent } from 'react';
import Sheet from '@/components/Sheet';
import TextField from '@/components/TextField';
import { Toggle } from '@/components/settings/SettingsRow';
import { parseAddresses } from '@/lib/emailTemplate';
import type { EmailDraft } from '@/lib/invoices';

export type EmailKind = 'invoice' | 'reminder';

type EmailSheetProps = {
    open: boolean;
    kind: EmailKind;
    invoiceId: number;
    draft: EmailDraft;
    onClose: () => void;
    onDone: (page: { props: unknown }) => void;
};

function EmailForm({ kind, invoiceId, draft, onClose, onDone }: Omit<EmailSheetProps, 'open'>) {
    const template = kind === 'reminder' ? draft.reminder : draft.invoice;
    const f = useForm({
        type: kind,
        to: draft.to,
        cc: draft.cc.join(', '),
        copy_to_me: draft.copy_to_me,
        subject: template.subject,
        body: template.body,
    });
    const errors = f.errors as Record<string, string | undefined>;
    const ccError = Object.entries(errors).find(([key]) => key.startsWith('cc'))?.[1];
    const title = kind === 'reminder' ? 'Poslat upomínku' : 'Odeslat e-mailem';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        f.transform((data) => ({ ...data, cc: parseAddresses(data.cc) }));
        f.post(`/faktury/${invoiceId}/odeslat`, {
            preserveScroll: true,
            only: [
                'invoice',
                'contact',
                'related',
                'email',
                'emails',
                'overdueInvoices',
                'status',
                'error',
            ],
            onSuccess: (page) => {
                onDone(page);
                onClose();
            },
        });
    };

    return (
        <form onSubmit={submit} className="af-sheet min-h-0 overflow-y-auto overscroll-contain">
            <div className="af-sheet__grabber" aria-hidden="true" />
            <h2 className="af-sheet__title">{title}</h2>

            {errors.send && (
                <div
                    role="alert"
                    className="bg-red-soft text-red rounded-[14px] px-4 py-3 text-[14px] leading-5"
                >
                    {errors.send}
                </div>
            )}

            <TextField
                label="Komu"
                type="email"
                value={f.data.to}
                onChange={(value) => f.setData('to', value)}
                error={errors.to}
                autoComplete="off"
                inputMode="email"
                required
            />
            <TextField
                label="Kopie (Cc)"
                value={f.data.cc}
                onChange={(value) => f.setData('cc', value)}
                error={ccError}
                hint="Více adres oddělte čárkou."
                autoComplete="off"
            />

            <div className="flex items-center justify-between gap-3 py-1">
                <span className="text-[15px]" id="email-copy-label">
                    Poslat kopii mně
                    {draft.copy_address && (
                        <span className="text-label-3"> · {draft.copy_address}</span>
                    )}
                </span>
                <Toggle
                    checked={f.data.copy_to_me}
                    label="Poslat kopii mně"
                    onChange={(checked) => f.setData('copy_to_me', checked)}
                />
            </div>

            <TextField
                label="Předmět"
                value={f.data.subject}
                onChange={(value) => f.setData('subject', value)}
                error={errors.subject}
                autoComplete="off"
                required
            />

            <div className="af-field">
                <label className="af-field__label" htmlFor="email-body">
                    Text e-mailu
                </label>
                <textarea
                    id="email-body"
                    rows={9}
                    className={`af-input ${errors.body ? 'af-input--error' : ''}`}
                    style={{
                        height: 'auto',
                        padding: '8px 12px',
                        lineHeight: '20px',
                        resize: 'vertical',
                    }}
                    value={f.data.body}
                    onChange={(event) => f.setData('body', event.target.value)}
                />
                {errors.body && (
                    <div className="af-field__error" role="alert">
                        {errors.body}
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-1.5">
                <span className="af-field__label">Příloha</span>
                <span className="bg-fill inline-flex max-w-full items-center gap-2 self-start rounded-full py-1.5 pr-3.5 pl-3 text-[14px]">
                    <FileText className="size-4 shrink-0" strokeWidth={1.75} aria-hidden="true" />
                    <span className="truncate">{draft.attachment}</span>
                </span>
            </div>

            <div className="af-sheet__actions">
                <button type="button" className="af-btn cursor-pointer" onClick={onClose}>
                    Zrušit
                </button>
                <button
                    type="submit"
                    className="af-btn af-btn--prominent cursor-pointer"
                    disabled={f.processing}
                >
                    {f.processing
                        ? 'Odesílám…'
                        : kind === 'reminder'
                          ? 'Poslat upomínku'
                          : 'Odeslat'}
                </button>
            </div>
        </form>
    );
}

/** Sheet „Odeslat e-mailem“ (faktura nebo upomínka): příjemce, kopie, předmět, text a příloha. */
export default function EmailSheet({ open, ...rest }: EmailSheetProps) {
    return (
        <Sheet
            open={open}
            onClose={rest.onClose}
            side="bottom"
            label={rest.kind === 'reminder' ? 'Poslat upomínku' : 'Odeslat e-mailem'}
        >
            {open && <EmailForm {...rest} />}
        </Sheet>
    );
}
