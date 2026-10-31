import { router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import SettingsRow, { SectionTitle } from '@/components/settings/SettingsRow';
import { formatDateTime } from '@/lib/format';
import type { SharedProps } from '@/lib/shared';
import type { BackupData, BackupEntry } from '@/lib/settings';

type BackupSectionProps = {
    backup: BackupData;
    onMessage: (message: string) => void;
};

function formatSize(bytes: number): string {
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB`;

    return `${Math.max(1, Math.round(bytes / 1024))} kB`;
}

function where(entry: BackupEntry): string {
    if (entry.remote) return 'Lokálně i off-site';

    return entry.local ? 'Jen lokálně' : '—';
}

export default function BackupSection({ backup, onMessage }: BackupSectionProps) {
    const [busy, setBusy] = useState(false);
    const last = backup.last;

    const run = () => {
        setBusy(true);
        router.post(
            '/nastaveni/zaloha',
            {},
            {
                preserveScroll: true,
                only: ['backup', 'backupWarning', 'status', 'error'],
                onSuccess: (page) => {
                    const props = page.props as unknown as SharedProps;
                    const error = props.error;
                    onMessage(
                        typeof error === 'string'
                            ? error
                            : (error?.message ?? props.status ?? 'Záloha hotová'),
                    );
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <section id="zaloha" aria-labelledby="h-zaloha" className="scroll-mt-5">
            <SectionTitle id="h-zaloha">Záloha a export dat</SectionTitle>

            {!backup.key_set && (
                <div
                    role="alert"
                    className="bg-red-soft text-red mb-3 rounded-[14px] px-4 py-3 text-[14px] leading-5"
                >
                    V .env chybí BACKUP_KEY, zálohy se nevytvoří. Postup je v docs/BACKUP.md.
                </div>
            )}
            {!backup.offsite && (
                <div
                    role="status"
                    className="bg-orange-soft text-orange mb-3 rounded-[14px] px-4 py-3 text-[14px] leading-5"
                >
                    Off-site úložiště není nastavené: zálohy zůstávají jen na tomto serveru. Doplňte
                    BACKUP_S3_* v .env (Backblaze B2).
                </div>
            )}

            <div className="af-list">
                <SettingsRow label="Poslední záloha">
                    <span className="py-1.5 text-[14px] leading-5">
                        {last ? (
                            <>
                                {formatDateTime(last.at)} · {formatSize(last.size)} · {where(last)}
                            </>
                        ) : (
                            <span className="text-label-3">Zatím žádná</span>
                        )}
                    </span>
                </SettingsRow>
                <SettingsRow label="Zálohovat teď">
                    <div className="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            className="af-btn af-btn--tinted cursor-pointer"
                            disabled={busy || !backup.key_set}
                            onClick={run}
                        >
                            {busy ? 'Zálohuji…' : 'Zálohovat teď'}
                        </button>
                        <span className="text-label-3 text-[13px]">Automaticky denně v 02:30.</span>
                    </div>
                </SettingsRow>
                <SettingsRow label="Export dat">
                    <div className="flex flex-wrap items-center gap-3">
                        <a
                            href="/nastaveni/export"
                            className="af-btn cursor-pointer no-underline"
                            download
                        >
                            <Download strokeWidth={1.75} aria-hidden="true" />
                            Exportovat data
                        </a>
                        <span className="text-label-3 text-[13px]">
                            Nešifrovaný ZIP: PDF faktur a CSV. Vytvoří se při stažení.
                        </span>
                    </div>
                </SettingsRow>
            </div>
            <div className="af-list-footer">
                Zálohy jsou šifrované klíčem BACKUP_KEY. Drží se 14 denních a jedna za měsíc po dobu
                10 let.
            </div>

            {backup.recent.length > 0 && (
                <>
                    <div className="af-list-header">Posledních {backup.recent.length} záloh</div>
                    <div className="af-list">
                        {backup.recent.map((entry) => (
                            <div key={entry.id} className="af-list__row">
                                <span className="af-list__label">
                                    {formatDateTime(entry.at)}
                                    {entry.status === 'failed' && entry.error && (
                                        <span className="text-red block text-[13px] leading-[18px]">
                                            {entry.error}
                                        </span>
                                    )}
                                </span>
                                {entry.status === 'ok' ? (
                                    <span className="text-label-2 text-[13px]">
                                        {formatSize(entry.size)} · {where(entry)}
                                    </span>
                                ) : (
                                    <span className="af-badge af-badge--red">Selhala</span>
                                )}
                            </div>
                        ))}
                    </div>
                </>
            )}
        </section>
    );
}
