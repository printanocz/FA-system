import { ChevronRight } from 'lucide-react';
import Sheet from '@/components/Sheet';
import { moreSections } from '@/lib/navigation';

type MoreSheetProps = {
    open: boolean;
    onClose: () => void;
};

/** Sheet „Další“ z TabBaru: zbylé položky menu jako seskupený seznam. */
export default function MoreSheet({ open, onClose }: MoreSheetProps) {
    return (
        <Sheet open={open} onClose={onClose} side="bottom" label="Další">
            <div className="af-sheet min-h-0 gap-3 overflow-y-auto overscroll-contain">
                <div className="af-sheet__grabber" aria-hidden="true" />
                <h2 className="af-sheet__title">Další</h2>

                {moreSections.map((section) => (
                    <section key={section.title ?? 'settings'}>
                        {section.title && <div className="af-list-header">{section.title}</div>}
                        <div className="af-list bg-surface-2">
                            {section.items.map((item) => {
                                const Icon = item.icon;
                                return (
                                    <a
                                        key={item.label}
                                        href={item.href ?? '#'}
                                        className="af-list__row text-label no-underline"
                                        onClick={(event) => {
                                            if (!item.href) event.preventDefault();
                                            onClose();
                                        }}
                                    >
                                        <Icon
                                            className="text-label-2 size-[18px] shrink-0"
                                            strokeWidth={1.75}
                                            aria-hidden="true"
                                        />
                                        <span className="af-list__label">{item.label}</span>
                                        <ChevronRight
                                            className="af-list__chevron"
                                            strokeWidth={1.75}
                                            aria-hidden="true"
                                        />
                                    </a>
                                );
                            })}
                        </div>
                    </section>
                ))}
            </div>
        </Sheet>
    );
}
