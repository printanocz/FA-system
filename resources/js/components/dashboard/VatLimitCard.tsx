import { TriangleAlert } from 'lucide-react';
import type { VatLimit } from '@/lib/dashboard';
import { formatMoney } from '@/lib/format';

/** Obrat za kalendářní rok vůči limitu pro registraci k DPH, od 80 % s oranžovým upozorněním. */
export default function VatLimitCard({ vat }: { vat: VatLimit }) {
    return (
        <article className="af-card flex flex-col gap-3">
            <h2 className="af-card__title">Limit pro registraci k DPH</h2>
            <div className="flex items-baseline justify-between gap-3">
                <span className="af-kpi__value">{vat.percent} %</span>
                <span className="text-label-2 text-right text-[13px] leading-[18px]">
                    {vat.exceeded ? 'Limit je překročen' : `Zbývá ${formatMoney(vat.remaining)}`}
                </span>
            </div>
            <div
                className={`af-meter ${vat.warning ? 'af-meter--warn' : ''}`}
                role="progressbar"
                aria-label={`Obrat ${vat.year} vůči limitu ${formatMoney(vat.limit)}`}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={Math.min(vat.percent, 100)}
            >
                <span style={{ width: `${Math.min(vat.percent, 100)}%` }} />
            </div>
            <p className="text-label-2 m-0 text-[13px] leading-[18px]">
                Obrat {vat.year}: {formatMoney(vat.turnover)} z {formatMoney(vat.limit)}
            </p>
            {vat.warning && (
                <div
                    role="status"
                    className="bg-orange-soft text-orange flex items-start gap-2 rounded-[14px] px-4 py-3 text-[14px] leading-5"
                >
                    <TriangleAlert
                        className="mt-0.5 size-4 shrink-0"
                        strokeWidth={2}
                        aria-hidden="true"
                    />
                    <span>
                        Při překročení {formatMoney(vat.limit)} se stanete plátcem od 1. 1. příštího
                        roku; při překročení {formatMoney(vat.immediate_limit)} už od následujícího
                        dne.
                    </span>
                </div>
            )}
        </article>
    );
}
