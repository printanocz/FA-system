import { usePage } from '@inertiajs/react';

/** Jednorázová zpráva ze serveru (např. „Odkaz jsme poslali“). */
export default function StatusMessage() {
    const { status } = usePage<{ status: string | null }>().props;

    if (!status) return null;

    return (
        <p className="body text-label-2" role="status">
            {status}
        </p>
    );
}
