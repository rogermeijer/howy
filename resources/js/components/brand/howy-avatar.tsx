import { BRAND_NAME } from '@/components/brand/brand';
import { cn } from '@/lib/utils';

/**
 * Howy as a colleague: an ink disc with the initial, and the wordmark's lime
 * dot as its status. `ring` is the ground it sits on, so the dot stands free.
 */
export function HowyAvatar({
    ring = 'panel',
    className,
}: {
    ring?: 'panel' | 'wash';
    className?: string;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'relative flex size-10 shrink-0 items-center justify-center rounded-full bg-cc-ink font-logo text-[17px] font-extrabold tracking-[-0.05em] text-cc-bg',
                className,
            )}
        >
            {BRAND_NAME.charAt(0)}
            <span
                className={cn(
                    'absolute right-0 bottom-0 size-[11px] rounded-full bg-cc-accent',
                    ring === 'panel'
                        ? 'shadow-[0_0_0_2px_var(--color-cc-panel)]'
                        : 'shadow-[0_0_0_2px_var(--color-cc-accent-tint)]',
                )}
            />
        </span>
    );
}
