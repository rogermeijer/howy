import { Plus } from 'lucide-react';
import {
    type ClipboardEvent,
    type KeyboardEvent,
    type ReactNode,
    useRef,
    useState,
} from 'react';
import {
    AccessPicker,
    useAccessLooks,
} from '@/components/mailboxes/sender-access';
import { shownEntry } from '@/components/mailboxes/sender-badge';
import { useTranslations } from '@/hooks/use-translations';
import { accessOf, type MailboxRules, parseSenders } from '@/lib/mailbox-rules';
import { cn } from '@/lib/utils';
import type { SenderAccess, SenderRule } from '@/types';

type Feedback = {
    added: number;
    moved: number;
    invalid: string[];
    publicDomains: string[];
};

/**
 * Where senders go on the list: type one, or paste a whole list (one per
 * line, separated by commas, or a mail's To line) and it goes in at once as
 * the access chosen beside it. A sender already listed moves to that access.
 * Typed, it says what Howy does with that sender now, so the field doubles
 * as a check. What is not an address or a domain stays to be put right.
 */
export function SenderAddField({
    rules,
    onChange,
    publicDomains,
}: {
    rules: MailboxRules;
    onChange: (senders: SenderRule[]) => void;
    publicDomains: string[];
}) {
    const t = useTranslations();
    const looks = useAccessLooks();
    const field = useRef<HTMLDivElement>(null);
    const [text, setText] = useState('');
    const [addAs, setAddAs] = useState<SenderAccess>('learn_answer');
    const [feedback, setFeedback] = useState<Feedback | null>(null);

    const add = (value: string, access: SenderAccess = addAs) => {
        if (value.trim() === '') {
            return;
        }

        const parsed = parseSenders(value, publicDomains);
        const next = [...rules.senders];
        let added = 0;
        let moved = 0;

        for (const entry of parsed.entries) {
            const index = next.findIndex((rule) => rule.entry === entry);

            if (index === -1) {
                next.push({ entry, access });
                added++;
            } else if (next[index].access !== access) {
                next[index] = { ...next[index], access };
                moved++;
            }
        }

        if (added + moved > 0) {
            onChange(next);
        }

        setFeedback({
            added,
            moved,
            invalid: parsed.invalid,
            publicDomains: parsed.publicDomains,
        });
        setText([...parsed.invalid, ...parsed.publicDomains].join(' '));
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        // Enter adds; it must never submit the rules half-typed.
        if (['Enter', ',', ';', ' '].includes(event.key)) {
            event.preventDefault();
            add(text);
        }
    };

    const onPaste = (event: ClipboardEvent<HTMLInputElement>) => {
        const input = event.currentTarget;
        const pasted = event.clipboardData.getData('text');
        const start = input.selectionStart ?? text.length;
        const end = input.selectionEnd ?? text.length;

        event.preventDefault();
        add(text.slice(0, start) + pasted + text.slice(end));
    };

    const pill = (access: SenderAccess) => (
        <span
            className={cn(
                'inline-flex rounded-full px-2 py-0.5 text-[12px] font-semibold',
                looks[access].pill,
            )}
        >
            {looks[access].label}
        </span>
    );

    const typed = text.trim() === '' ? null : parseSenders(text, publicDomains);
    const single =
        typed !== null &&
        typed.entries.length === 1 &&
        typed.invalid.length === 0 &&
        typed.publicDomains.length === 0
            ? typed.entries[0]
            : null;

    let line: ReactNode;

    if (single !== null) {
        const listed = rules.senders.find((rule) => rule.entry === single);
        const now = listed
            ? { access: listed.access, because: 'address' as const }
            : single.includes('@')
              ? accessOf(rules, single)
              : single === rules.domain.toLowerCase() && !rules.publicDomain
                ? { access: rules.domainAccess, because: 'colleagues' as const }
                : { access: 'ignore' as const, because: 'others' as const };
        const because = {
            address: t('on the list'),
            domain: t('through @:domain on the list', {
                domain: single.slice(single.lastIndexOf('@') + 1),
            }),
            colleagues: t('as a colleague'),
            others: t('not on the list'),
            invalid: '',
        }[now.because];

        line =
            listed && listed.access === addAs ? (
                <>
                    <span>
                        {t(':sender is on the list as', {
                            sender: shownEntry(single),
                        })}
                    </span>
                    {pill(addAs)}
                </>
            ) : (
                <>
                    <span>{t('Now:')}</span>
                    {pill(now.access)}
                    <span>{because}.</span>
                    <span>
                        {listed ? t('Enter sets it to') : t('Enter adds it as')}
                    </span>
                    {pill(addAs)}
                </>
            );
    } else if (typed !== null && typed.entries.length > 1) {
        line = (
            <>
                <span>
                    {t('Enter adds :count as', {
                        count: typed.entries.length,
                    })}
                </span>
                {pill(addAs)}
            </>
        );
    } else if (feedback !== null) {
        line = [
            ...(feedback.added > 0
                ? [t(':count added', { count: feedback.added })]
                : []),
            ...(feedback.moved > 0
                ? [
                      t(':count set to ":access"', {
                          count: feedback.moved,
                          access: looks[addAs].label,
                      }),
                  ]
                : []),
            ...(feedback.invalid.length > 0
                ? [
                      t('not recognised: :entries', {
                          entries: feedback.invalid.join(', '),
                      }),
                  ]
                : []),
            ...feedback.publicDomains.map((domain) =>
                t(':value is a public domain: add the addresses one by one.', {
                    value: domain,
                }),
            ),
        ].join(' · ');
    } else {
        line = t(
            'Paste a whole list at once, even straight from the To line of a mail.',
        );
    }

    const picker = (
        <AccessPicker
            value={addAs}
            onChange={(access) => {
                setAddAs(access);
                // Typed first, then chose how: add it as that.
                add(text, access);
            }}
            label={t('Add as')}
            size="sm"
        />
    );

    // On a phone the choice sits under the field, so the field keeps its width.
    return (
        <div ref={field} className="flex flex-col gap-1.5">
            <div className="flex h-[46px] items-center gap-2 rounded-full border-[1.5px] border-cc-border-strong bg-cc-panel pr-1.5 pl-3.5 transition-colors focus-within:border-cc-ink">
                <Plus
                    className="size-4 shrink-0 text-cc-subtle"
                    strokeWidth={2.25}
                />
                <input
                    value={text}
                    onChange={(event) => {
                        setText(event.target.value);
                        setFeedback(null);
                    }}
                    onKeyDown={onKeyDown}
                    onPaste={onPaste}
                    onBlur={(event) => {
                        // Not when moving on to choose the access.
                        if (
                            !field.current?.contains(
                                event.relatedTarget as Node | null,
                            )
                        ) {
                            add(text);
                        }
                    }}
                    aria-label={t('Add addresses or domains')}
                    placeholder={t('Paste or type addresses and domains…')}
                    className="min-w-0 flex-1 border-none bg-transparent text-[14px] text-cc-ink outline-none placeholder:text-cc-faint"
                />
                <span className="hidden shrink-0 items-center gap-2 text-[13px] text-cc-subtle sm:flex">
                    {t('as')}
                    {picker}
                </span>
            </div>
            <span className="flex items-center gap-2 px-3.5 text-[13px] whitespace-nowrap text-cc-subtle sm:hidden">
                {t('Add as')}
                {picker}
            </span>
            <p
                role="status"
                className="flex flex-wrap items-center gap-x-1.5 gap-y-1 px-3.5 text-[12.5px] leading-[1.45] text-cc-subtle"
            >
                {line}
            </p>
        </div>
    );
}
