import { X } from 'lucide-react';
import { formatMoney, plural } from '@/lib/format';

type BulkBarProps = {
    count: number;
    total: number;
    busy: boolean;
    onPay: () => void;
    onClear: () => void;
};

/** Plovoucí skleněná lišta nad vybranými fakturami (komponenta z artboardu Faktury). */
export default function BulkBar({ count, total, busy, onPay, onClear }: BulkBarProps) {
    return (
        <div
            role="toolbar"
            aria-label="Akce pro vybrané faktury"
            className="af-glass sticky bottom-6 z-10 flex flex-wrap items-center gap-2 self-center rounded-full py-1.5 pr-1.5 pl-[18px]"
        >
            <span className="mr-1.5 text-[14px] font-semibold">
                {count} {plural(count, 'vybraná', 'vybrané', 'vybraných')} · {formatMoney(total)}
            </span>
            <button
                type="button"
                className="af-btn af-btn--tinted cursor-pointer"
                disabled={busy}
                onClick={onPay}
            >
                Označit jako uhrazené
            </button>
            <button type="button" className="af-btn" disabled title="Přijde s PDF">
                Stáhnout PDF
            </button>
            <button
                type="button"
                className="af-btn af-btn--icon cursor-pointer"
                aria-label="Zrušit výběr"
                onClick={onClear}
            >
                <X strokeWidth={2} aria-hidden="true" />
            </button>
        </div>
    );
}
