import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import {
    BookOpen,
    Check,
    ChevronDown,
    MessageSquareReply,
    X,
} from 'lucide-react';
import type { ComponentProps } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { ACCESSES, answers, learns } from '@/lib/mailbox-rules';
import { cn } from '@/lib/utils';
import type { SenderAccess } from '@/types';

export type AccessLook = {
    label: string;
    description: string;
    /** The cc-tag variant, so an access has one colour wherever it shows. */
    tag: string;
    /** Background and text of a pill in that colour. */
    pill: string;
    /** The text colour alone. */
    text: string;
    /** The border that bounds a pill you can click. */
    border: string;
    dot: string;
};

/**
 * What each access is called and looks like. The labels are the same strings
 * as SenderAccess::label() on the server.
 */
export function useAccessLooks(): Record<SenderAccess, AccessLook> {
    const t = useTranslations();

    return {
        learn_answer: {
            label: t('Learn and answer'),
            description: t(
                'Learns from their mail and answers their questions.',
            ),
            tag: 'cc-tag-decision',
            pill: 'bg-cc-decision-bg text-cc-decision-fg',
            text: 'text-cc-decision-fg',
            border: 'border-cc-decision-fg/30',
            dot: 'bg-cc-decision-fg',
        },
        learn: {
            label: t('Learn only'),
            description: t('Learns from their mail, never writes to them.'),
            tag: 'cc-tag-knowledge',
            pill: 'bg-cc-knowledge-bg text-cc-knowledge-fg',
            text: 'text-cc-knowledge-fg',
            border: 'border-cc-knowledge-fg/30',
            dot: 'bg-cc-knowledge-fg',
        },
        answer: {
            label: t('Answer only'),
            description: t(
                'Answers their questions, learns nothing from them.',
            ),
            tag: 'cc-tag-question',
            pill: 'bg-cc-question-bg text-cc-question-fg',
            text: 'text-cc-question-fg',
            border: 'border-cc-question-fg/30',
            dot: 'bg-cc-question-fg',
        },
        ignore: {
            label: t('Ignore'),
            description: t('Their mail is stored, Howy does nothing with it.'),
            tag: 'cc-tag-noise',
            pill: 'bg-cc-noise-bg text-cc-noise-fg',
            text: 'text-cc-noise-fg',
            border: 'border-cc-noise-fg/30',
            dot: 'bg-cc-noise-fg',
        },
    };
}

export function AccessTag({
    access,
    className,
    ...props
}: { access: SenderAccess } & ComponentProps<'span'>) {
    const look = useAccessLooks()[access];

    // The rest of the props pass on, so a tooltip can hang on the tag.
    return (
        <span {...props} className={cn('cc-tag', look.tag, className)}>
            {look.label}
        </span>
    );
}

/**
 * Learns ✓ · Answers ✗: what an access means, at a glance.
 */
export function AccessMeaning({ access }: { access: SenderAccess }) {
    const t = useTranslations();
    const part = (on: boolean, Icon: typeof BookOpen, label: string) => (
        <span
            className={cn(
                'inline-flex items-center gap-1',
                on ? 'text-cc-ink' : 'text-cc-faint line-through',
            )}
        >
            <Icon className="size-3.5 shrink-0" />
            {label}
            {on ? (
                <Check className="size-3.5 shrink-0 text-cc-decision-fg" />
            ) : (
                <X className="size-3.5 shrink-0" />
            )}
        </span>
    );

    return (
        <span className="inline-flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] font-medium">
            {part(learns(access), BookOpen, t('Learns'))}
            {part(answers(access), MessageSquareReply, t('Answers'))}
        </span>
    );
}

/**
 * The four accesses as menu choices: a dot in its colour, its name, what it
 * means when asked for, and a check on the chosen one.
 */
export function AccessMenuItems({
    value,
    onChange,
    described = false,
}: {
    value: SenderAccess | null;
    onChange: (access: SenderAccess) => void;
    described?: boolean;
}) {
    const looks = useAccessLooks();

    return (
        <DropdownMenuPrimitive.RadioGroup
            value={value ?? ''}
            onValueChange={(access) => onChange(access as SenderAccess)}
            className="flex flex-col gap-0.5"
        >
            {ACCESSES.map((access) => (
                <DropdownMenuPrimitive.RadioItem
                    key={access}
                    value={access}
                    className={cn(
                        'flex cursor-default items-start gap-2.5 rounded-[10px] px-2.5 py-2 outline-none select-none focus:bg-cc-bg',
                        value === access && looks[access].pill,
                    )}
                >
                    <span
                        className={cn(
                            'mt-[5px] size-2 shrink-0 rounded-full',
                            looks[access].dot,
                        )}
                    />
                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span className="text-[13.5px] font-semibold text-cc-ink">
                            {looks[access].label}
                        </span>
                        {described && (
                            <span className="text-[12px] leading-snug text-cc-subtle">
                                <WithHowy text={looks[access].description} />
                            </span>
                        )}
                    </span>
                    <DropdownMenuPrimitive.ItemIndicator className="mt-0.5 shrink-0">
                        <Check
                            className="size-4 text-cc-ink"
                            strokeWidth={2.5}
                        />
                    </DropdownMenuPrimitive.ItemIndicator>
                </DropdownMenuPrimitive.RadioItem>
            ))}
        </DropdownMenuPrimitive.RadioGroup>
    );
}

/**
 * A button wholly in the colour of the chosen access, opening the four.
 */
export function AccessPicker({
    value,
    onChange,
    label,
    size = 'md',
}: {
    value: SenderAccess;
    onChange: (access: SenderAccess) => void;
    /** What is being chosen, for screen readers. */
    label: string;
    size?: 'sm' | 'md';
}) {
    const look = useAccessLooks()[value];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={`${label}: ${look.label}`}
                    className={cn(
                        'inline-flex shrink-0 items-center gap-1 rounded-full border-[1.5px] font-semibold whitespace-nowrap transition-[filter] hover:brightness-95 focus-visible:ring-2 focus-visible:ring-cc-ink/40 focus-visible:outline-none',
                        size === 'md'
                            ? 'h-9 pr-2.5 pl-3.5 text-[13px]'
                            : 'h-[30px] pr-2 pl-3 text-[12.5px]',
                        look.pill,
                        look.border,
                    )}
                >
                    {look.label}
                    <ChevronDown
                        className={size === 'md' ? 'size-4' : 'size-3.5'}
                        strokeWidth={2.25}
                    />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-80 rounded-[14px] border-cc-border p-1.5 shadow-[0_12px_32px_rgba(13,27,30,0.12)]"
            >
                <AccessMenuItems value={value} onChange={onChange} described />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
