import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, Languages } from 'lucide-react';
import { Flag } from '@/components/flag';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { update as updateLocale } from '@/routes/locale';
import type { LocalizationProps } from '@/types';

type Props = {
    /** `label`: icon and language name. `flag`: the current flag only. */
    variant?: 'label' | 'flag';
    /** Which way the menu opens; `top` for a picker in a footer. */
    side?: 'top' | 'bottom';
    className?: string;
};

/**
 * Language picker for screens a guest can reach.
 *
 * Signed-in users get the durable control in their profile; this writes the same
 * preference, so the two stay in step.
 */
export function LanguageSwitcher({
    variant = 'label',
    side = 'bottom',
    className,
}: Props) {
    const { locale, locales } = usePage<LocalizationProps>().props;
    const t = useTranslations();

    const current = locales.find((option) => option.value === locale);
    const flags = variant === 'flag';

    const choose = (value: string) => {
        if (value === locale) {
            return;
        }

        router.put(
            updateLocale().url,
            { locale: value },
            { preserveScroll: true },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={t('Change language')}
                    title={flags ? current?.label : undefined}
                    className={cn(
                        'flex h-10 shrink-0 cursor-pointer items-center gap-2 rounded-[10px] border-[1.5px] border-cc-border bg-cc-panel px-3 text-sm font-medium text-cc-ink transition-colors hover:border-cc-border-strong',
                        flags && 'rounded-full pr-2.5 pl-3',
                        className,
                    )}
                >
                    {flags ? (
                        <>
                            <Flag locale={locale} />
                            <ChevronDown className="size-4 text-cc-muted" />
                        </>
                    ) : (
                        <>
                            <Languages className="size-[18px]" />
                            {current?.label ?? locale}
                        </>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" side={side} className="w-44">
                {locales.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        className="cursor-pointer"
                        onSelect={() => choose(option.value)}
                    >
                        <Check
                            className={cn(
                                'mr-2 size-4 shrink-0',
                                option.value === locale
                                    ? 'text-cc-accent-deep'
                                    : 'invisible',
                            )}
                        />
                        {flags && (
                            <Flag locale={option.value} className="mr-2" />
                        )}
                        <span>{option.label}</span>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
