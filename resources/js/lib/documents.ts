import { plural } from '@/lib/format';

export type DocumentBadge = { label: string; className: string };

const badge = (label: string, tone = ''): DocumentBadge => ({
    label,
    className: `af-badge${tone ? ` af-badge--${tone}` : ''}`,
});

/** Štítek stavu dokladu. „Po splatnosti“ a „expirovaná“ jsou počítané stavy a mají přednost. */
export function documentBadge(
    status: string,
    computed: string | null,
    overdueDays?: number | null,
): DocumentBadge {
    if (computed === 'overdue') {
        return badge(
            overdueDays && overdueDays > 0
                ? `Po splatnosti ${overdueDays} ${plural(overdueDays, 'den', 'dny', 'dní')}`
                : 'Po splatnosti',
            'red',
        );
    }
    if (computed === 'expired') return badge('Expirovaná', 'orange');

    switch (status) {
        case 'draft':
            return badge('Koncept', 'outline');
        case 'issued':
            return badge('Vystaveno');
        case 'sent':
            return badge('Odesláno', 'blue');
        case 'partially_paid':
            return badge('Částečně uhrazeno', 'orange');
        case 'paid':
            return badge('Uhrazeno', 'green');
        case 'cancelled':
            return badge('Stornováno', 'strike');
        case 'created':
            return badge('Vytvořena');
        case 'ordered':
            return badge('Objednáno', 'green');
        case 'invoiced':
            return badge('Vyfakturováno', 'blue');
        case 'rejected':
            return badge('Zamítnuto', 'red');
        default:
            return badge(status);
    }
}

/** Popis dokladu, když nemá položky. */
export function documentTypeLabel(type: string): string {
    switch (type) {
        case 'invoice':
            return 'Faktura';
        case 'proforma':
            return 'Zálohová faktura';
        case 'credit_note':
            return 'Dobropis';
        case 'quote':
            return 'Cenová nabídka';
        default:
            return 'Doklad';
    }
}
