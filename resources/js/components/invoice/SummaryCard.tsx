import { Link } from '@inertiajs/react';
import { Toggle } from '@/components/settings/SettingsRow';
import type { LiveTotals } from '@/hooks/useInvoiceTotals';
import { plural } from '@/lib/format';
import { formatDecimal, type BankAccountOption } from '@/lib/invoice';

type SummaryCardProps = {
    totals: LiveTotals;
    discount: string;
    discountError?: string;
    roundTotal: boolean;
    vatPayer: boolean;
    paymentMethod: 'bank_transfer' | 'cash';
    account: BankAccountOption | null;
    constantSymbol: string;
    onDiscountChange: (value: string) => void;
    onRoundChange: (value: boolean) => void;
};

const Amount = ({ value }: { value: string }) => (
    <span className="text-right tabular-nums">{formatDecimal(value)} Kč</span>
);

/** Souhrn (sticky vpravo, na mobilu dole): celkem k úhradě, sleva, zaokrouhlení a platební údaje. */
export default function SummaryCard({
    totals,
    discount,
    discountError,
    roundTotal,
    vatPayer,
    paymentMethod,
    account,
    constantSymbol,
    onDiscountChange,
    onRoundChange,
}: SummaryCardProps) {
    const discountAmount = Number(totals.discount_total);

    return (
        <aside
            className="flex min-w-0 flex-[1_1_320px] flex-col gap-4 desktop:sticky desktop:top-5"
            aria-label="Souhrn faktury"
        >
            <div className="af-card flex flex-col gap-3.5">
                <p className="af-card__title">Celkem k úhradě</p>
                <div
                    className="text-[34px] leading-10 font-semibold tracking-[-0.5px] tabular-nums"
                    aria-live="polite"
                    data-confirmed={totals.confirmed}
                >
                    {formatDecimal(totals.total)}{' '}
                    <span className="text-label-2 text-[20px] font-medium tracking-normal">Kč</span>
                </div>

                <div className="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-2 text-[14px] leading-[19px]">
                    <span className="text-label-2">
                        Mezisoučet, {totals.count}{' '}
                        {plural(totals.count, 'položka', 'položky', 'položek')}
                    </span>
                    <Amount value={totals.subtotal} />

                    <label className="text-label-2 flex items-center gap-2" htmlFor="f-discount">
                        Sleva
                        <span className="relative inline-flex w-[84px]">
                            <input
                                id="f-discount"
                                className={`af-input af-input--amount h-8 pr-7 ${discountError ? 'af-input--error' : ''}`}
                                inputMode="decimal"
                                value={discount}
                                aria-invalid={discountError ? true : undefined}
                                onChange={(event) => onDiscountChange(event.target.value)}
                            />
                            <span className="text-label-3 pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 text-[13px]">
                                %
                            </span>
                        </span>
                    </label>
                    <Amount value={discountAmount > 0 ? `-${totals.discount_total}` : '0'} />

                    <span className="text-label-2 flex items-center gap-2">
                        Zaokrouhlení
                        <Toggle
                            checked={roundTotal}
                            onChange={onRoundChange}
                            label="Zaokrouhlit celkem na celé koruny"
                        />
                    </span>
                    <Amount value={totals.rounding} />
                </div>
                {discountError && (
                    <div className="af-field__error" role="alert">
                        {discountError}
                    </div>
                )}

                <div className="bg-separator h-px" />
                <span className="text-label-3 text-xs leading-4">
                    {vatPayer ? 'Plátce DPH. ' : 'Nejsem plátce DPH. '}Měna CZK.
                </span>
            </div>

            <div>
                <div className="af-list-header">Platební údaje</div>
                <div className="af-list">
                    <div className="af-list__row">
                        <span className="af-list__label">Účet</span>
                        {paymentMethod === 'cash' ? (
                            <span className="af-list__value">Platba v hotovosti</span>
                        ) : account ? (
                            <span className="af-list__value af-mono">{account.number}</span>
                        ) : (
                            <Link
                                href="/nastaveni#ucty"
                                className="af-list__value text-accent-text"
                            >
                                Přidat v Nastavení
                            </Link>
                        )}
                    </div>
                    <div className="af-list__row">
                        <span className="af-list__label">Variabilní symbol</span>
                        <span className="af-list__value">Přidělí se při vystavení</span>
                    </div>
                    <div className="af-list__row">
                        <span className="af-list__label">Konstantní symbol</span>
                        <span className="af-list__value af-mono">{constantSymbol || '—'}</span>
                    </div>
                    <div className="af-list__row">
                        <span className="af-list__label">QR Platba</span>
                        <span className="af-list__value">Přidá se při vystavení</span>
                    </div>
                </div>
            </div>
        </aside>
    );
}
