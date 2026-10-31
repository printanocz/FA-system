import { router } from '@inertiajs/react';
import { Check, Ellipsis } from 'lucide-react';
import Menu, { type MenuItem } from '@/components/Menu';
import { documentBadge } from '@/lib/documents';
import { formatDate, formatPrice } from '@/lib/format';
import { downloadFile, pdfUrl, type InvoiceRow } from '@/lib/invoices';

export const invoiceHref = (row: InvoiceRow) =>
    row.status === 'draft' ? `/faktury/${row.id}/upravit` : `/faktury/${row.id}`;

export type RowActions = {
    onPay: (row: InvoiceRow) => void;
    onDelete: (row: InvoiceRow) => void;
    onCopy: (row: InvoiceRow) => void;
};

/** Položky menu ⋯ u řádku faktury; odeslání přijde s T-11. */
export function rowMenu(row: InvoiceRow, actions: RowActions): MenuItem[] {
    const items: MenuItem[] = [
        { label: 'Otevřít', onSelect: () => router.visit(invoiceHref(row)) },
    ];
    if (row.status === 'draft') {
        items.push({
            label: 'Upravit',
            onSelect: () => router.visit(`/faktury/${row.id}/upravit`),
        });
    }
    items.push({ label: 'Kopírovat jako novou', onSelect: () => actions.onCopy(row) });
    if (row.can_pay) {
        items.push({ label: 'Označit jako uhrazené', onSelect: () => actions.onPay(row) });
    }
    items.push(
        {
            label: 'Stáhnout PDF',
            onSelect: () => downloadFile(pdfUrl(row.id, true)),
            separatorBefore: true,
        },
        { label: 'Odeslat', disabled: true, hint: 'Přijde brzy' },
    );
    if (row.can_delete) {
        items.push({
            label: 'Smazat',
            destructive: true,
            separatorBefore: true,
            onSelect: () => actions.onDelete(row),
        });
    }

    return items;
}

export function Checkbox({
    checked,
    label,
    onChange,
}: {
    checked: boolean;
    label: string;
    onChange: () => void;
}) {
    return (
        <button
            type="button"
            role="checkbox"
            aria-checked={checked}
            aria-label={label}
            className="af-check cursor-pointer border-0 bg-transparent p-0"
            onClick={(event) => {
                event.stopPropagation();
                onChange();
            }}
        >
            {checked && <Check strokeWidth={3} aria-hidden="true" />}
        </button>
    );
}

type InvoiceTableProps = {
    rows: InvoiceRow[];
    selected: number[];
    onToggle: (id: number) => void;
    onToggleAll: () => void;
    actions: RowActions;
};

/** Tabulka faktur (desktop a tablet): výběr, číslo, odběratel s popisem, data, částka, stav a menu. */
export default function InvoiceTable({
    rows,
    selected,
    onToggle,
    onToggleAll,
    actions,
}: InvoiceTableProps) {
    const allSelected = rows.length > 0 && rows.every((row) => selected.includes(row.id));

    return (
        <div className="af-table-wrap">
            <table className="af-table">
                <thead>
                    <tr>
                        <th className="w-7">
                            <Checkbox
                                checked={allSelected}
                                label="Vybrat všechny faktury na stránce"
                                onChange={onToggleAll}
                            />
                        </th>
                        <th>Číslo</th>
                        <th>Odběratel</th>
                        <th>Vystaveno</th>
                        <th>Splatnost</th>
                        <th className="is-num">Částka</th>
                        <th>Stav</th>
                        <th className="w-10">
                            <span className="sr-only">Akce</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => {
                        const badge = documentBadge(
                            row.status,
                            row.computed_status,
                            row.overdue_days,
                        );
                        const isSelected = selected.includes(row.id);
                        const name = row.number ?? 'koncept';

                        return (
                            <tr
                                key={row.id}
                                aria-selected={isSelected}
                                className="cursor-pointer"
                                onClick={() => router.visit(invoiceHref(row))}
                            >
                                <td>
                                    <Checkbox
                                        checked={isSelected}
                                        label={`Vybrat fakturu ${name}`}
                                        onChange={() => onToggle(row.id)}
                                    />
                                </td>
                                <td>
                                    <span className="af-mono">{row.number ?? '—'}</span>
                                </td>
                                <td className="max-w-[320px]">
                                    <div className="af-cell-main truncate">
                                        {row.contact ?? '—'}
                                    </div>
                                    {row.description && (
                                        <div className="af-cell-sub truncate">
                                            {row.description}
                                        </div>
                                    )}
                                </td>
                                <td className="whitespace-nowrap">
                                    {row.issue_date ? formatDate(row.issue_date) : '—'}
                                </td>
                                <td
                                    className={`whitespace-nowrap ${row.computed_status === 'overdue' ? 'is-overdue' : ''}`}
                                >
                                    {row.due_date ? formatDate(row.due_date) : '—'}
                                </td>
                                <td className="is-num">{formatPrice(row.total, row.currency)}</td>
                                <td>
                                    <span className={`${badge.className} whitespace-nowrap`}>
                                        {badge.label}
                                    </span>
                                </td>
                                <td onClick={(event) => event.stopPropagation()}>
                                    <Menu
                                        label={`Akce faktury ${name}`}
                                        className="af-btn af-btn--icon af-btn--sm cursor-pointer"
                                        items={rowMenu(row, actions)}
                                    >
                                        <Ellipsis strokeWidth={2} aria-hidden="true" />
                                    </Menu>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
