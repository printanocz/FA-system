import { router, useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import ConfirmSheet from '@/components/ConfirmSheet';
import TextField from '@/components/TextField';
import { CURRENCIES, UNITS, priceToInput, type PriceItemRow } from '@/lib/priceItems';

type PriceItemFormProps = {
    /** `null` = nová položka. */
    item: PriceItemRow | null;
    onClose: () => void;
    onToast: (message: string) => void;
    /** Panel na desktopu má vlastní nadpis a tlačítko Zavřít, v Sheetu je nadpis dialogu. */
    variant: 'panel' | 'sheet';
};

const CUSTOM = '__custom';

/** Formulář položky ceníku. Stejný v panelu vpravo (desktop) i v Sheetu (mobil). */
export default function PriceItemForm({ item, onClose, onToast, variant }: PriceItemFormProps) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: item?.name ?? '',
        note: item?.note ?? '',
        code: item?.code ?? '',
        unit: item ? item.unit : 'ks',
        currency: item?.currency ?? 'CZK',
        unit_price: item ? priceToInput(item.unit_price) : '',
    });
    const [customUnit, setCustomUnit] = useState(
        item !== null && item.unit !== '' && !(UNITS as readonly string[]).includes(item.unit),
    );
    const [confirmDelete, setConfirmDelete] = useState(false);

    const symbol = CURRENCIES.find((currency) => currency.code === data.currency)?.symbol ?? '';
    const title = item ? 'Upravit položku' : 'Nová položka';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, preserveState: true };

        if (item) {
            put(`/cenik/${item.id}`, {
                ...options,
                onSuccess: () => onToast('Uloženo'),
            });
        } else {
            post('/cenik', {
                ...options,
                onSuccess: () => {
                    onClose();
                    onToast('Položka vytvořena');
                },
            });
        }
    };

    const cancel = () => {
        reset();
        clearErrors();
        setCustomUnit(false);
        onClose();
    };

    const remove = () =>
        router.delete(`/cenik/${item?.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmDelete(false);
                onClose();
                onToast(item?.is_used ? 'Položka archivována' : 'Položka smazána');
            },
        });

    const restore = () =>
        router.post(
            `/cenik/${item?.id}/obnovit`,
            {},
            { preserveScroll: true, onSuccess: () => onToast('Položka obnovena') },
        );

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-col gap-4 overflow-y-auto">
            {variant === 'panel' ? (
                <div className="flex items-center justify-between">
                    <h2 className="m-0 text-[20px] leading-[25px] font-semibold">{title}</h2>
                    <button
                        type="button"
                        className="af-btn af-btn--icon af-btn--sm"
                        aria-label="Zavřít"
                        onClick={cancel}
                    >
                        <X strokeWidth={2} aria-hidden="true" />
                    </button>
                </div>
            ) : (
                <>
                    <div className="af-sheet__grabber" aria-hidden="true" />
                    <h2 className="af-sheet__title">{title}</h2>
                </>
            )}

            <TextField
                label="Název"
                value={data.name}
                onChange={(value) => setData('name', value)}
                error={errors.name}
                autoComplete="off"
                required
            />
            <TextField
                label="Popis na faktuře (nepovinné)"
                value={data.note}
                onChange={(value) => setData('note', value)}
                error={errors.note}
                autoComplete="off"
            />

            <div className="grid grid-cols-3 gap-3">
                <TextField
                    label="Kód"
                    value={data.code}
                    onChange={(value) => setData('code', value)}
                    error={errors.code}
                    className="af-mono"
                    autoComplete="off"
                />
                <div className="af-field">
                    <label className="af-field__label" htmlFor="pi-unit">
                        MJ
                    </label>
                    <select
                        id="pi-unit"
                        className="af-input"
                        value={customUnit ? CUSTOM : data.unit}
                        onChange={(event) => {
                            if (event.target.value === CUSTOM) {
                                setCustomUnit(true);
                                setData('unit', '');
                            } else {
                                setCustomUnit(false);
                                setData('unit', event.target.value);
                            }
                        }}
                    >
                        <option value="">—</option>
                        {UNITS.map((unit) => (
                            <option key={unit} value={unit}>
                                {unit}
                            </option>
                        ))}
                        <option value={CUSTOM}>Jiná…</option>
                    </select>
                </div>
                <div className="af-field">
                    <label className="af-field__label" htmlFor="pi-currency">
                        Měna
                    </label>
                    <select
                        id="pi-currency"
                        className="af-input"
                        value={data.currency}
                        onChange={(event) => setData('currency', event.target.value)}
                    >
                        {CURRENCIES.map((currency) => (
                            <option key={currency.code} value={currency.code}>
                                {currency.code}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            {errors.currency && (
                <div className="af-field__error" role="alert">
                    {errors.currency}
                </div>
            )}

            {customUnit && (
                <TextField
                    label="Vlastní měrná jednotka"
                    value={data.unit}
                    onChange={(value) => setData('unit', value)}
                    error={errors.unit}
                    maxLength={20}
                    autoComplete="off"
                />
            )}

            <div className="af-field">
                <label className="af-field__label" htmlFor="pi-price">
                    Cena za MJ
                </label>
                <span className="af-input-wrap">
                    <input
                        id="pi-price"
                        className={`af-input af-input--amount ${errors.unit_price ? 'af-input--error' : ''}`}
                        inputMode="decimal"
                        value={data.unit_price}
                        onChange={(event) => setData('unit_price', event.target.value)}
                        placeholder="0,00"
                        aria-invalid={errors.unit_price ? true : undefined}
                    />
                    <span className="af-input-wrap__suffix">{symbol}</span>
                </span>
                {errors.unit_price && (
                    <div className="af-field__error" role="alert">
                        {errors.unit_price}
                    </div>
                )}
            </div>

            <div className="flex flex-wrap justify-between gap-2">
                {item === null ? (
                    <span />
                ) : item.archived ? (
                    <button type="button" className="af-btn" onClick={restore}>
                        Obnovit
                    </button>
                ) : (
                    <button
                        type="button"
                        className="af-btn af-btn--destructive"
                        onClick={() => setConfirmDelete(true)}
                    >
                        {item.is_used ? 'Archivovat' : 'Smazat'}
                    </button>
                )}
                <div className="flex gap-2">
                    <button type="button" className="af-btn af-btn--plain" onClick={cancel}>
                        Zrušit
                    </button>
                    <button
                        type="submit"
                        className={`af-btn ${variant === 'sheet' ? 'af-btn--prominent' : 'af-btn--tinted'}`}
                        disabled={processing}
                    >
                        Uložit
                    </button>
                </div>
            </div>

            {item && (
                <ConfirmSheet
                    open={confirmDelete}
                    title={item.is_used ? 'Archivovat položku?' : 'Smazat položku?'}
                    text={
                        item.is_used
                            ? 'Položka je na dokladech, proto se nesmaže. Přestane se nabízet na fakturách a najdete ji pod „Zobrazit archivované“.'
                            : 'Položka není na žádném dokladu a smaže se nadobro.'
                    }
                    confirmLabel={item.is_used ? 'Archivovat' : 'Smazat položku'}
                    destructive
                    onConfirm={remove}
                    onCancel={() => setConfirmDelete(false)}
                />
            )}
        </form>
    );
}
