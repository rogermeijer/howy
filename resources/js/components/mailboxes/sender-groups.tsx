import { useAccessLooks } from '@/components/mailboxes/sender-access';
import { SenderBadge } from '@/components/mailboxes/sender-badge';
import { useTranslations } from '@/hooks/use-translations';
import { ACCESSES } from '@/lib/mailbox-rules';
import { cn } from '@/lib/utils';
import type { SenderAccess, SenderRule } from '@/types';

/** A group shows this many senders; the rest are behind its +N. */
const SHOWN = 3;

/**
 * The senders on a mailbox's list, grouped by what Howy may do with their
 * mail, newest first. A group shows its first three; +N opens all of them.
 * Read-only without onChange.
 */
export function SenderGroups({
    senders,
    onChange,
    errors,
    onShowAll,
}: {
    senders: SenderRule[];
    onChange?: (senders: SenderRule[]) => void;
    errors: Record<string, string>;
    onShowAll: (access: SenderAccess) => void;
}) {
    const t = useTranslations();
    const looks = useAccessLooks();

    const errorAt = (index: number) =>
        errors[`sender_rules.${index}.entry`] ??
        errors[`sender_rules.${index}.access`];

    return (
        <div className="flex flex-col gap-3.5">
            {ACCESSES.map((access) => {
                const group = senders
                    .map((rule, index) => ({ rule, index }))
                    .filter(({ rule }) => rule.access === access)
                    .reverse();

                if (group.length === 0) {
                    return null;
                }

                return (
                    <div key={access} className="flex flex-col gap-2">
                        <p className="flex items-center gap-2 text-[13px] font-semibold">
                            <span
                                className={cn(
                                    'size-2 rounded-full',
                                    looks[access].dot,
                                )}
                            />
                            {looks[access].label}
                            <span className="font-medium text-cc-subtle">
                                {group.length}
                            </span>
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                            {group.slice(0, SHOWN).map(({ rule, index }) => (
                                <SenderBadge
                                    key={rule.entry}
                                    rule={rule}
                                    error={errorAt(index)}
                                    onChange={
                                        onChange &&
                                        ((next) =>
                                            onChange(
                                                senders.map((other, at) =>
                                                    at === index
                                                        ? {
                                                              ...other,
                                                              access: next,
                                                          }
                                                        : other,
                                                ),
                                            ))
                                    }
                                    onRemove={
                                        onChange &&
                                        (() =>
                                            onChange(
                                                senders.filter(
                                                    (_, at) => at !== index,
                                                ),
                                            ))
                                    }
                                    onCreatesColleagues={
                                        onChange &&
                                        ((on) =>
                                            onChange(
                                                senders.map((other, at) =>
                                                    at === index
                                                        ? {
                                                              ...other,
                                                              createsColleagues:
                                                                  on,
                                                          }
                                                        : other,
                                                ),
                                            ))
                                    }
                                />
                            ))}
                            {group.length > SHOWN && (
                                <button
                                    type="button"
                                    onClick={() => onShowAll(access)}
                                    aria-label={t(
                                        'Show all :count with ":access"',
                                        {
                                            count: group.length,
                                            access: looks[access].label,
                                        },
                                    )}
                                    className={cn(
                                        'h-[30px] rounded-full border-[1.5px] bg-cc-panel px-3 text-[13px] font-bold hover:brightness-95',
                                        looks[access].border,
                                        looks[access].text,
                                    )}
                                >
                                    +{group.length - SHOWN}
                                </button>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
