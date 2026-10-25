import { Head, Link, useForm, useHttp, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import ConfirmSheet from '@/components/ConfirmSheet';
import AccountSection from '@/components/settings/AccountSection';
import BankAccountsSection from '@/components/settings/BankAccountsSection';
import CompanySection from '@/components/settings/CompanySection';
import DocumentsSection from '@/components/settings/DocumentsSection';
import NumberSeriesSection from '@/components/settings/NumberSeriesSection';
import { GroupedNav, SideNav } from '@/components/settings/SettingsNav';
import { useToast } from '@/components/Toast';
import Toolbar from '@/components/Toolbar';
import { useHash } from '@/hooks/useHash';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useUnsavedGuard } from '@/hooks/useUnsavedGuard';
import { MOBILE_QUERY } from '@/lib/breakpoints';
import {
    countChanges,
    sections,
    type CompanyValues,
    type SectionId,
    type SettingsPageProps,
} from '@/lib/settings';

type AresResult = Partial<Pick<CompanyValues, 'name' | 'ico' | 'dic' | 'street' | 'zip' | 'city'>>;

const sectionIds = sections.filter((section) => !section.soon).map((section) => section.id);
const asSection = (hash: string): SectionId | null =>
    (sectionIds as string[]).includes(hash) ? (hash as SectionId) : null;

export default function SettingsIndex({
    company,
    documents,
    bankAccounts,
    numberSeries,
    account,
}: SettingsPageProps) {
    const { url } = usePage();
    const isMobile = useMediaQuery(MOBILE_QUERY);
    const hash = useHash();
    const active = asSection(hash);
    const { show, toast } = useToast();

    const form = useForm({ company, documents });
    const ares = useHttp<{ ico: string }, AresResult>('post', '/nastaveni/ares', { ico: '' });
    const [spy, setSpy] = useState<SectionId>('firma');

    const changes =
        countChanges(form.data.company, company) + countChanges(form.data.documents, documents);
    const dirty = changes > 0;
    const guard = useUnsavedGuard(dirty);

    // Zvýraznění sekce v postranní navigaci podle toho, která je právě vidět.
    useEffect(() => {
        if (isMobile) return;

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.find((entry) => entry.isIntersecting);
                if (visible) setSpy(visible.target.id as SectionId);
            },
            { rootMargin: '-10% 0px -70% 0px' },
        );
        sectionIds.forEach((id) => {
            const element = document.getElementById(id);
            if (element) observer.observe(element);
        });

        return () => observer.disconnect();
    }, [isMobile, url]);

    const error = (field: string) => (form.errors as Record<string, string | undefined>)[field];

    const setCompany = <K extends keyof CompanyValues>(field: K, value: CompanyValues[K]) =>
        form.setData('company', { ...form.data.company, [field]: value });

    const save = () =>
        form.put('/nastaveni', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const fresh = page.props as unknown as SettingsPageProps;
                form.setData({ company: fresh.company, documents: fresh.documents });
                show('Uloženo');
            },
        });

    const discard = () => {
        form.setData({ company, documents });
        form.clearErrors();
        ares.clearErrors();
    };

    const loadFromAres = async () => {
        ares.clearErrors();
        ares.transform(() => ({ ico: form.data.company.ico }));
        try {
            const result = await ares.post('/nastaveni/ares');
            form.setData('company', { ...form.data.company, ...result });
        } catch {
            // Chyba je v ares.errors.ico a zobrazí se u pole IČO.
        }
    };

    const visible = (id: SectionId) => !isMobile || active === id;
    const showActions = !isMobile || active === 'firma' || active === 'doklady' || dirty;
    const activeLabel = sections.find((section) => section.id === active)?.label;

    return (
        <>
            <Head title="Nastavení" />
            <Toolbar title="Nastavení">
                {showActions && (
                    <>
                        {dirty && (
                            <span className="text-label-3 text-[13px]" aria-live="polite">
                                Neuložené změny: {changes}
                            </span>
                        )}
                        <button
                            type="button"
                            className="af-btn"
                            disabled={!dirty}
                            onClick={discard}
                        >
                            Zahodit
                        </button>
                        <button
                            type="button"
                            className="af-btn af-btn--prominent"
                            disabled={!dirty || form.processing}
                            onClick={save}
                        >
                            Uložit změny
                        </button>
                    </>
                )}
            </Toolbar>

            <div className="flex flex-wrap items-start gap-6">
                {isMobile ? active === null && <GroupedNav /> : <SideNav current={spy} />}

                {(!isMobile || active !== null) && (
                    <div className="flex min-w-0 flex-[999_1_520px] flex-col gap-7">
                        {isMobile && (
                            <Link
                                href="/nastaveni"
                                preserveState
                                preserveScroll
                                className="text-accent-text -mb-3 inline-flex items-center gap-1 text-[15px] no-underline"
                            >
                                <ChevronLeft
                                    className="size-4"
                                    strokeWidth={2}
                                    aria-hidden="true"
                                />
                                Nastavení{activeLabel ? ` · ${activeLabel}` : ''}
                            </Link>
                        )}

                        {visible('firma') && (
                            <CompanySection
                                values={form.data.company}
                                onChange={setCompany}
                                error={error}
                                onLoadAres={loadFromAres}
                                aresBusy={ares.processing}
                                aresError={(ares.errors as Record<string, string | undefined>).ico}
                            />
                        )}
                        {visible('ucty') && (
                            <BankAccountsSection accounts={bankAccounts} onSaved={show} />
                        )}
                        {visible('rady') && (
                            <NumberSeriesSection series={numberSeries} onSaved={show} />
                        )}
                        {visible('doklady') && (
                            <DocumentsSection
                                values={form.data.documents}
                                onChange={(field, value) =>
                                    form.setData('documents', {
                                        ...form.data.documents,
                                        [field]: value,
                                    })
                                }
                                error={error}
                            />
                        )}
                        {visible('ucet') && <AccountSection account={account} onSaved={show} />}
                    </div>
                )}
            </div>

            <ConfirmSheet
                open={guard.pending}
                title="Neuložené změny"
                text="Máte neuložené změny. Když odejdete, přijdete o ně."
                confirmLabel="Zahodit změny"
                cancelLabel="Zůstat"
                destructive
                onConfirm={guard.leave}
                onCancel={guard.stay}
            />
            {toast}
        </>
    );
}
