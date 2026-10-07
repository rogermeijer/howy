import { type ReactNode, useId, useState } from 'react';
import { Plus } from 'lucide-react';
import { cn } from '@/lib/utils';

const motion = 'duration-300 ease-out motion-reduce:transition-none';

/**
 * A question that opens to its answer, for FAQ-style lists. The answer
 * slides open (grid rows 0fr → 1fr) and fades in; the plus turns into a
 * lime ×. Closed answers are inert, so they are skipped by keyboard and
 * screen readers.
 */
export function Disclosure({
    question,
    children,
    defaultOpen = false,
}: {
    question: ReactNode;
    children: ReactNode;
    defaultOpen?: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const panelId = useId();

    return (
        <div className="border-b border-cc-border">
            <h3 className="m-0">
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={panelId}
                    onClick={() => setOpen((value) => !value)}
                    className="group flex w-full cursor-pointer items-center justify-between gap-4 py-6 text-left text-[19px] font-bold tracking-[-0.02em] text-cc-ink"
                >
                    {question}
                    <span
                        aria-hidden="true"
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-full transition-[background-color,rotate]',
                            motion,
                            open
                                ? 'rotate-45 bg-cc-accent'
                                : 'bg-transparent group-hover:bg-cc-accent-tint',
                        )}
                    >
                        <Plus className="size-[22px]" strokeWidth={2} />
                    </span>
                </button>
            </h3>
            <div
                id={panelId}
                role="region"
                inert={!open}
                className={cn(
                    'grid transition-[grid-template-rows,opacity]',
                    motion,
                    open
                        ? 'grid-rows-[1fr] opacity-100'
                        : 'grid-rows-[0fr] opacity-0',
                )}
            >
                <div className="overflow-hidden">
                    <div
                        className={cn(
                            'max-w-[660px] pb-6 text-cc-muted transition-transform',
                            motion,
                            open ? 'translate-y-0' : '-translate-y-2',
                        )}
                    >
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
