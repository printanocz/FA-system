import { useForm } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { SectionTitle, Toggle } from '@/components/settings/SettingsRow';
import Sheet from '@/components/Sheet';
import TextField from '@/components/TextField';
import type { NumberSeriesData } from '@/lib/settings';

function SeriesForm({
    series,
    onClose,
    onSaved,
}: {
    series: NumberSeriesData;
    onClose: () => void;
    onSaved: (message: string) => void;
}) {
    const { data, setData, put, processing, errors } = useForm({
        name: series.name,
        prefix: series.prefix,
        format: series.format,
        next_number: String(series.next_number),
        reset_yearly: series.reset_yearly,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(`/nastaveni/rady/${series.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                onClose();
                onSaved('Uloženo');
            },
        });
    };

    return (
        <form onSubmit={submit} className="af-sheet min-h-0 overflow-y-auto overscroll-contain">
            <div className="af-sheet__grabber" aria-hidden="true" />
            <h2 className="af-sheet__title">Upravit číselnou řadu</h2>

            <TextField
                label="Název"
                value={data.name}
                onChange={(value) => setData('name', value)}
                error={errors.name}
                required
            />
            <div className="grid grid-cols-[96px_minmax(0,1fr)] gap-3">
                <TextField
                    label="Prefix"
                    value={data.prefix}
                    onChange={(value) => setData('prefix', value)}
                    error={errors.prefix}
                    className="af-mono"
                    placeholder="N"
                />
                <TextField
                    label="Formát"
                    value={data.format}
                    onChange={(value) => setData('format', value)}
                    error={errors.format}
                    className="af-mono"
                    hint="{YYYY} rok, {YY} rok dvoumístně, {NNN} pořadí doplněné nulami"
                    required
                />
            </div>
            <TextField
                label="Příští číslo"
                value={data.next_number}
                onChange={(value) => setData('next_number', value.replace(/\D/g, ''))}
                error={errors.next_number}
                inputMode="numeric"
                className="af-mono"
                hint={`Teď by se přidělilo ${series.preview}. Pořadí jde posunout jen dopředu.`}
                required
            />
            <div className="flex items-center justify-between gap-3">
                <span>Nová řada každý rok</span>
                <Toggle
                    checked={data.reset_yearly}
                    label="Nová řada každý rok"
                    onChange={(checked) => setData('reset_yearly', checked)}
                />
            </div>

            <div className="af-sheet__actions">
                <button type="button" className="af-btn" onClick={onClose}>
                    Zrušit
                </button>
                <button type="submit" className="af-btn af-btn--prominent" disabled={processing}>
                    Uložit
                </button>
            </div>
        </form>
    );
}

export default function NumberSeriesSection({
    series,
    onSaved,
}: {
    series: NumberSeriesData[];
    onSaved: (message: string) => void;
}) {
    const [editing, setEditing] = useState<NumberSeriesData | null>(null);
    const close = () => setEditing(null);

    return (
        <section id="rady" aria-labelledby="h-rady" className="scroll-mt-5">
            <SectionTitle id="h-rady">Číselné řady</SectionTitle>
            <div className="af-list">
                {series.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        className="af-list__row w-full cursor-pointer border-0 bg-transparent text-left font-[inherit] text-[length:inherit]"
                        onClick={() => setEditing(item)}
                    >
                        <div className="af-list__label">
                            <div className="text-label font-semibold">{item.name}</div>
                            <div className="text-label-3 text-xs">
                                Formát{' '}
                                <span className="af-mono">
                                    {item.prefix}
                                    {item.format}
                                </span>
                                {item.reset_yearly ? ', nová řada každý rok' : ''}
                            </div>
                        </div>
                        <span className="text-label-2 max-tablet:hidden text-[13px]">
                            Příští číslo
                        </span>
                        <span className="af-mono text-label font-semibold">{item.preview}</span>
                        <ChevronRight
                            className="af-list__chevron"
                            strokeWidth={2}
                            aria-hidden="true"
                        />
                    </button>
                ))}
            </div>
            <div className="af-list-footer">
                Řadu lze navázat na číslování z iDokladu: nastavte příští číslo. Čísla se přidělují
                až při vystavení.
            </div>

            <Sheet open={editing !== null} onClose={close} side="bottom" label="Číselná řada">
                {editing !== null && (
                    <SeriesForm
                        key={editing.id}
                        series={editing}
                        onClose={close}
                        onSaved={onSaved}
                    />
                )}
            </Sheet>
        </section>
    );
}
