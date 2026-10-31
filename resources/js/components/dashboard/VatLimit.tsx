import { TriangleAlert } from 'lucide-react';
import type { VatLimit } from '@/lib/dashboard';
import { moneyParts } from '@/lib/dashboard';
import { formatMoney } from '@/lib/format';

/** Varování od 80 % limitu (oranžové), oba prahy z config/vat.php. */
function VatWarning({ vat }: { vat: VatLimit }) {
    if (!vat.warning) return null;

    return (
        <div
            role="status"
            className="bg-orange-soft text-orange flex items-start gap-2 rounded-[14px] px-4 py-3 text-[14px] leading-5"
        >
            <TriangleAlert className="mt-0.5 size-4 shrink-0" strokeWidth={2} aria-hidden="true" />
            <span>
                Při překročení {formatMoney(vat.limit)} se stanete plátcem od 1. 1. příštího roku;
                při překročení {formatMoney(vat.immediate_limit)} už od následujícího dne.
            </span>
        </div>
    );
}

function Meter({ vat }: { vat: VatLimit }) {
    return (
        <div
            className={`af-meter ${vat.warning ? 'af-meter--warn' : ''}`}
            role="img"
            aria-label={`Obrat ${formatMoney(vat.turnover)} z limitu ${formatMoney(vat.limit)}`}
        >
            <span style={{ width: `${Math.min(vat.percent, 100)}%` }} />
        </div>
    );
}

/** Desktop: karta `af-kpi` s procentem, metrem a větou „… z … za kalendářní rok. Zbývá …“. */
export function VatCard({ vat }: { vat: VatLimit }) {
    return (
        <div className="af-card af-kpi">
            <h2 className="af-card__title">Limit pro registraci k DPH</h2>
            <div className="af-kpi__value">
                {vat.percent} <small>%</small>
            </div>
            <Meter vat={vat} />
            <div className="af-kpi__delta">
                {formatMoney(vat.turnover)} z {formatMoney(vat.limit)} za kalendářní rok.{' '}
                {vat.exceeded ? 'Limit je překročen.' : `Zbývá ${formatMoney(vat.remaining)}.`}
            </div>
            <VatWarning vat={vat} />
        </div>
    );
}

/** Mobil: `af-list-header` + `af-list` s řádkem „610 300 Kč / z 2 000 000 Kč“, metrem a varováním. */
export function VatList({ vat }: { vat: VatLimit }) {
    const turnover = moneyParts(vat.turnover);

    return (
        <section aria-labelledby="vat-title">
            <h2 id="vat-title" className="af-list-header m-0 font-normal">
                Limit pro registraci k DPH
            </h2>
            <div className="af-list">
                <div className="af-list__row flex-col items-stretch gap-2">
                    <div className="flex justify-between text-[14px]">
                        <span>
                            {turnover.value} {turnover.unit}
                        </span>
                        <span className="text-label-3">z {formatMoney(vat.limit)}</span>
                    </div>
                    <Meter vat={vat} />
                    {vat.exceeded && (
                        <span className="text-label-2 text-[13px]">Limit je překročen.</span>
                    )}
                    <VatWarning vat={vat} />
                </div>
            </div>
        </section>
    );
}
