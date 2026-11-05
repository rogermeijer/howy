import {
    ArrowDown,
    ArrowRight,
    Ban,
    Check,
    Database,
    Ear,
    Eye,
    KeyRound,
    type LucideIcon,
    Mail,
    MessageSquareReply,
    Paperclip,
    Repeat,
    ScrollText,
    Send,
    ShieldCheck,
    Sparkles,
    UserCheck,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { WithHowy } from '@/components/brand/howy-name';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/hooks/use-translations';
import {
    accepts,
    addressFor,
    maySendTo,
    type MailboxRules,
} from '@/lib/mailbox-rules';
import { cn } from '@/lib/utils';
import type { MailFacts } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rules: MailboxRules;
    /** The account's name: whose members see the mail. */
    company: string;
    facts: MailFacts;
    /** Above the explanation, to explain another mailbox. */
    picker?: ReactNode;
    /** A line on whose rules these are: being edited, or an example. */
    note?: string;
};

type Translate = ReturnType<typeof useTranslations>;

/**
 * Everything a mailbox does with mail, for the people whose mail it is: whose
 * mail Howy reads, what it does in To and in CC, who gets a reply, and what
 * happens under the hood. Every example is worked out from the mailbox's own
 * rules (lib/mailbox-rules mirrors Mailbox::accepts), so what it shows is
 * what this mailbox would do.
 */
export function MailboxExplainerDialog({
    open,
    onOpenChange,
    rules,
    company,
    facts,
    picker,
    note,
}: Props) {
    const t = useTranslations();
    const people = examplePeople(rules, t);
    const percent = facts.answerConfidence;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] flex-col gap-0 overflow-hidden rounded-2xl bg-cc-panel p-0 sm:max-w-[800px]">
                <div className="flex flex-col gap-3 border-b border-cc-border px-6 pt-6 pb-5 sm:px-8">
                    <div className="flex items-center gap-3 pr-8">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-cc-accent-tint text-cc-accent-deep">
                            <ShieldCheck className="size-5" />
                        </div>
                        <DialogTitle className="cc-subtitle">
                            <WithHowy text={t('What Howy does with mail')} />
                        </DialogTitle>
                    </div>
                    <DialogDescription className="cc-caption">
                        <WithHowy
                            text={t(
                                'Exactly what happens to mail sent to :address: what Howy reads, when it replies, and what happens under the hood.',
                                { address: rules.address },
                            )}
                        />
                    </DialogDescription>
                    {picker}
                    <div className="flex flex-wrap items-center gap-2">
                        <Address address={rules.address} tone="mailbox" />
                        <span className="cc-tag cc-tag-noise">
                            {t('Reads: :rule', {
                                rule: {
                                    domain: `@${rules.domain}`,
                                    whitelist: t('the whitelist'),
                                    always: t('everyone'),
                                }[rules.policy],
                            })}
                        </span>
                        <span
                            className={cn(
                                'cc-tag',
                                rules.repliesEnabled
                                    ? 'cc-tag-decision'
                                    : 'cc-tag-pending',
                            )}
                        >
                            {rules.repliesEnabled
                                ? t('Replies: on')
                                : t('Replies: off')}
                        </span>
                    </div>
                    {note && <p className="cc-caption italic">{note}</p>}
                </div>

                <div className="flex min-h-0 flex-col gap-10 overflow-y-auto px-6 py-7 sm:px-8">
                    <InShort rules={rules} t={t} />
                    <Steps company={company} t={t} />
                    <ToOrCc
                        rules={rules}
                        people={people}
                        percent={percent}
                        t={t}
                    />
                    <WhoIsRead rules={rules} people={people} t={t} />
                    <WhoGetsReply rules={rules} people={people} t={t} />
                    <Examples
                        rules={rules}
                        people={people}
                        percent={percent}
                        t={t}
                    />
                    <UnderTheHood company={company} facts={facts} t={t} />
                </div>
            </DialogContent>
        </Dialog>
    );
}

type People = {
    /** Someone the rules most likely let in, who asks Howy things. */
    asker: string;
    /** A second person on the mail, also likely let in. */
    colleague: string;
    /** Someone from outside, usually kept out. */
    outsider: string;
    /** Someone on the blacklist, when the blacklist counts and has entries. */
    blocked: string | null;
    newsletter: string;
};

/**
 * Who the examples are about, made from the mailbox's own domain and lists.
 * Whether each is let in is worked out from the rules, never assumed.
 */
function examplePeople(rules: MailboxRules, t: Translate): People {
    const [first, second] = rules.whitelist;
    const whitelisted = rules.policy === 'whitelist' && first !== undefined;
    const domainOf = (entry: string) =>
        addressFor(entry, 'x').split('@')[1] ?? rules.domain;

    return {
        asker: whitelisted ? addressFor(first, 'anna') : `anna@${rules.domain}`,
        colleague:
            whitelisted && second !== undefined
                ? addressFor(second, 'piet')
                : `piet@${whitelisted ? domainOf(first) : rules.domain}`,
        outsider: t('customer@example.com'),
        blocked:
            rules.policy !== 'whitelist' && rules.blacklist[0] !== undefined
                ? addressFor(rules.blacklist[0], 'info')
                : null,
        newsletter: t('newsletter@shop.example'),
    };
}

function SectionTitle({
    number,
    title,
    intro,
}: {
    number: number;
    title: string;
    intro?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex items-center gap-2.5">
                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-cc-ink text-[12px] font-bold text-cc-bg">
                    {number}
                </span>
                <h3 className="text-[17px] leading-tight font-semibold tracking-[-0.01em]">
                    <WithHowy text={title} />
                </h3>
            </div>
            {intro && <p className="cc-caption pl-[34px]">{intro}</p>}
        </div>
    );
}

/**
 * An address as a pill: the mailbox in lime, people in grey, and someone left
 * off a reply struck through.
 */
function Address({
    address,
    tone = 'person',
}: {
    address: string;
    tone?: 'mailbox' | 'person' | 'left-out';
}) {
    return (
        <span
            className={cn(
                'inline-flex max-w-full items-center gap-1 truncate rounded-full px-2.5 py-0.5 text-[12px] font-medium',
                tone === 'mailbox' &&
                    'bg-cc-accent-tint font-semibold text-cc-accent-deep ring-1 ring-cc-accent-deep/25',
                tone === 'person' && 'bg-cc-raised text-cc-ink',
                tone === 'left-out' &&
                    'bg-cc-raised text-cc-faint line-through decoration-cc-faint',
            )}
        >
            {tone === 'mailbox' && <Mail className="size-3 shrink-0" />}
            <span className="truncate">{address}</span>
        </span>
    );
}

/**
 * A mail as its envelope and first line, the mailbox highlighted wherever it
 * stands on it.
 */
function MailCard({
    from,
    to,
    cc = [],
    subject,
    body,
    mailbox,
    t,
    className,
}: {
    from: string;
    to: string[];
    cc?: string[];
    subject: string;
    body?: string;
    mailbox: string;
    t: Translate;
    className?: string;
}) {
    const tone = (address: string) =>
        address === mailbox ? 'mailbox' : 'person';
    const row = (label: string, addresses: string[]) => (
        <div className="flex min-w-0 items-start gap-2">
            <span className="w-[42px] shrink-0 pt-0.5 text-[11px] font-semibold tracking-[0.06em] text-cc-faint uppercase">
                {label}
            </span>
            <div className="flex min-w-0 flex-wrap gap-1">
                {addresses.map((address) => (
                    <Address
                        key={address}
                        address={address}
                        tone={tone(address)}
                    />
                ))}
            </div>
        </div>
    );

    return (
        <div
            className={cn(
                'flex flex-col gap-1.5 rounded-xl border border-cc-border bg-cc-panel p-3 shadow-xs',
                className,
            )}
        >
            {row(t('From:'), [from])}
            {row(t('To:'), to)}
            {cc.length > 0 && row(t('CC:'), cc)}
            <div className="mt-1 border-t border-cc-border pt-2">
                <p className="text-[13px] font-semibold">{subject}</p>
                {body && (
                    <p className="text-[13px] leading-[1.5] text-cc-subtle">
                        {body}
                    </p>
                )}
            </div>
        </div>
    );
}

function Point({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <li className="flex gap-2.5 text-[13px] leading-[1.5]">
            <Icon className="mt-0.5 size-4 shrink-0 text-cc-subtle" />
            <span>{children}</span>
        </li>
    );
}

function InShort({ rules, t }: { rules: MailboxRules; t: Translate }) {
    const tiles: { icon: LucideIcon; text: string }[] = [
        {
            icon: Eye,
            text: t('Reads only mail from senders the rules let in.'),
        },
        rules.repliesEnabled
            ? {
                  icon: MessageSquareReply,
                  text: t(
                      'Replies only when it is in To, and only when it is sure enough.',
                  ),
              }
            : {
                  icon: Ban,
                  text: t('Never sends anything: replies are off.'),
              },
        {
            icon: Ear,
            text: t('In CC or BCC it only listens: it never writes to anyone.'),
        },
        {
            icon: UserCheck,
            text: t('Nothing from mail counts until a person approves it.'),
        },
        {
            icon: ShieldCheck,
            text: t('Never changes, moves or deletes anything in the mailbox.'),
        },
    ];

    return (
        <section className="flex flex-col gap-4">
            <SectionTitle number={1} title={t('In short')} />
            <ul className="grid gap-2.5 sm:grid-cols-2">
                {tiles.map(({ icon: Icon, text }) => (
                    <li
                        key={text}
                        className="flex items-start gap-3 rounded-xl bg-cc-bg p-3.5"
                    >
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-cc-panel text-cc-accent-deep shadow-xs">
                            <Icon className="size-4" />
                        </span>
                        <span className="text-[14px] leading-[1.45] font-medium">
                            {text}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Steps({ company, t }: { company: string; t: Translate }) {
    const steps: { title: string; body: ReactNode }[] = [
        {
            title: t('The mail arrives'),
            body: (
                <WithHowy
                    text={t(
                        'Gmail lets Howy know right away. Howy stores every mail, also the ones it ignores, for the members of :company to see.',
                        { company },
                    )}
                />
            ),
        },
        {
            title: t('Does Howy read it?'),
            body: (
                <div className="flex flex-col gap-2">
                    <span>{t('Only when all of these hold:')}</span>
                    <ul className="flex flex-col gap-1.5">
                        {[
                            t(
                                'The sender is let in by the rules of this mailbox.',
                            ),
                            t(
                                'A person wrote it: auto-replies, newsletters and mailing lists are skipped.',
                            ),
                            t('It was not sent by the mailbox itself.'),
                            t(
                                'It came in live: mail you import is stored for reference, but never read or answered.',
                            ),
                        ].map((condition) => (
                            <li key={condition} className="flex gap-2">
                                <Check className="mt-0.5 size-3.5 shrink-0 text-cc-decision-fg" />
                                <span>{condition}</span>
                            </li>
                        ))}
                    </ul>
                    <span className="flex gap-2 rounded-lg bg-cc-noise-bg px-2.5 py-1.5 text-cc-noise-fg">
                        <X className="mt-0.5 size-3.5 shrink-0" />
                        {t(
                            'If not, it stops here: no AI, nothing for the knowledge base, no reply.',
                        )}
                    </span>
                </div>
            ),
        },
        {
            title: t('Where is Howy on the mail?'),
            body: (
                <div className="flex flex-wrap gap-2">
                    <span className="cc-tag cc-tag-question">
                        <span>
                            <WithHowy text={t('In To: Howy is asked')} />
                        </span>
                    </span>
                    <span className="cc-tag cc-tag-knowledge">
                        <span>
                            <WithHowy text={t('In CC or BCC: Howy listens')} />
                        </span>
                    </span>
                </div>
            ),
        },
        {
            title: t('Howy acts'),
            body: (
                <WithHowy
                    text={t(
                        'What it does depends on its place on the mail and on what the mail is: a question, information, or neither.',
                    )}
                />
            ),
        },
    ];

    return (
        <section className="flex flex-col gap-4">
            <SectionTitle
                number={2}
                title={t('From incoming mail to action')}
            />
            <ol className="flex flex-col">
                {steps.map((step, index) => (
                    <li key={step.title} className="flex gap-3.5">
                        <div className="flex flex-col items-center">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full border-[1.5px] border-cc-accent-deep bg-cc-accent-tint text-[12px] font-bold text-cc-accent-deep">
                                {index + 1}
                            </span>
                            {index < steps.length - 1 && (
                                <span className="my-1 w-px flex-1 bg-cc-border-strong" />
                            )}
                        </div>
                        <div className="flex flex-col gap-1 pb-5 text-[13px] leading-[1.5]">
                            <p className="pt-1 text-[14px] font-semibold">
                                <WithHowy text={step.title} />
                            </p>
                            <div className="text-cc-subtle">{step.body}</div>
                        </div>
                    </li>
                ))}
            </ol>
        </section>
    );
}

function Outcome({
    tag,
    tone,
    children,
}: {
    tag: string;
    tone: 'question' | 'knowledge' | 'action' | 'noise';
    children: ReactNode;
}) {
    return (
        <li className="flex flex-col items-start gap-1.5">
            <span className={`cc-tag cc-tag-${tone}`}>{tag}</span>
            <span className="text-[13px] leading-[1.5]">{children}</span>
        </li>
    );
}

function ToOrCc({
    rules,
    people,
    percent,
    t,
}: {
    rules: MailboxRules;
    people: People;
    percent: number;
    t: Translate;
}) {
    const proposed = t(
        'Proposed for the knowledge base; it only counts once someone approves it.',
    );

    return (
        <section className="flex flex-col gap-4">
            <SectionTitle
                number={3}
                title={t('In To or in CC: the difference')}
            />
            <div className="grid gap-3 md:grid-cols-2">
                <div className="flex flex-col gap-3 rounded-2xl border-[1.5px] border-cc-question-fg/25 bg-cc-question-bg/40 p-4">
                    <div>
                        <p className="flex items-center gap-2 text-[15px] font-semibold text-cc-question-fg">
                            <MessageSquareReply className="size-4" />
                            <span>
                                <WithHowy text={t('Howy in To')} />
                            </span>
                        </p>
                        <p className="cc-caption">
                            <WithHowy text={t('Howy is asked something.')} />
                        </p>
                    </div>
                    <MailCard
                        from={people.asker}
                        to={[rules.address]}
                        subject={t('Opening hours over the holidays?')}
                        mailbox={rules.address}
                        t={t}
                    />
                    <ul className="flex flex-col gap-3">
                        <Outcome tag={t('Question')} tone="question">
                            <WithHowy
                                text={
                                    rules.repliesEnabled
                                        ? t(
                                              'Howy looks it up in the approved knowledge. At least :percent% sure: it replies to the sender. Less sure: no reply, the answer waits in Howy for a colleague. Nothing found: it replies that it does not know.',
                                              { percent },
                                          )
                                        : t(
                                              'Howy looks it up in the approved knowledge and keeps the answer in Howy. Replies are off, so nothing is sent.',
                                          )
                                }
                            />
                        </Outcome>
                        <Outcome tag={t('Information')} tone="knowledge">
                            {proposed}{' '}
                            <WithHowy
                                text={
                                    rules.repliesEnabled
                                        ? t(
                                              'If it contradicts the knowledge base, Howy replies to say so.',
                                          )
                                        : t(
                                              'If it contradicts the knowledge base, that is flagged in Howy.',
                                          )
                                }
                            />
                        </Outcome>
                        <Outcome tag={t('Only Howy in To')} tone="action">
                            {t(
                                'A request to write something from the knowledge, such as a summary or a paragraph, is answered as a question.',
                            )}
                        </Outcome>
                    </ul>
                </div>

                <div className="flex flex-col gap-3 rounded-2xl border-[1.5px] border-cc-knowledge-fg/25 bg-cc-knowledge-bg/40 p-4">
                    <div>
                        <p className="flex items-center gap-2 text-[15px] font-semibold text-cc-knowledge-fg">
                            <Ear className="size-4" />
                            <span>
                                <WithHowy text={t('Howy in CC or BCC')} />
                            </span>
                        </p>
                        <p className="cc-caption">
                            <WithHowy text={t('Howy only listens in.')} />
                        </p>
                    </div>
                    <MailCard
                        from={people.asker}
                        to={[people.outsider]}
                        cc={[rules.address]}
                        subject={t('New rates')}
                        mailbox={rules.address}
                        t={t}
                    />
                    <ul className="flex flex-col gap-3">
                        <Outcome tag={t('Never writes')} tone="noise">
                            {t(
                                'No reply and no mail to anyone, whatever the mail says.',
                            )}
                        </Outcome>
                        <Outcome tag={t('Question')} tone="question">
                            <WithHowy
                                text={t(
                                    'Meant for the people it was asked: Howy does nothing with it.',
                                )}
                            />
                        </Outcome>
                        <Outcome tag={t('Information')} tone="knowledge">
                            {proposed}{' '}
                            <WithHowy
                                text={t(
                                    'A contradiction is only flagged in Howy.',
                                )}
                            />
                        </Outcome>
                    </ul>
                </div>
            </div>
            <p className="flex gap-2.5 rounded-xl bg-cc-bg px-3.5 py-3 text-[13px] leading-[1.5]">
                <Paperclip className="mt-0.5 size-4 shrink-0 text-cc-subtle" />
                <span>
                    <WithHowy
                        text={t(
                            'Attachments, in both cases: a new file is proposed for the knowledge base as a document and waits for approval, like an upload. A file Howy already has gets this mail as one more source.',
                        )}
                    />
                </span>
            </p>
        </section>
    );
}

function Verdict({ read, reason }: { read: boolean; reason?: string }) {
    const t = useTranslations();

    return (
        <span className="flex shrink-0 items-center gap-2">
            {reason && <span className="cc-caption">{reason}</span>}
            <span
                className={cn(
                    'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[12px] font-semibold',
                    read
                        ? 'bg-cc-decision-bg text-cc-decision-fg'
                        : 'bg-cc-noise-bg text-cc-noise-fg',
                )}
            >
                {read ? (
                    <Check className="size-3.5" />
                ) : (
                    <X className="size-3.5" />
                )}
                {read ? t('Read') : t('Ignored')}
            </span>
        </span>
    );
}

function WhoIsRead({
    rules,
    people,
    t,
}: {
    rules: MailboxRules;
    people: People;
    t: Translate;
}) {
    const intro = {
        domain: t(
            'Howy reads mail from addresses at @:domain only, except those on the blacklist.',
            { domain: rules.domain },
        ),
        whitelist:
            rules.whitelist.length > 0
                ? t(
                      'Howy reads mail only from the addresses and domains on the whitelist.',
                  )
                : t('The whitelist is empty, so Howy reads no mail at all.'),
        always: t(
            'Howy reads mail from everyone, except those on the blacklist.',
        ),
    }[rules.policy];

    const list = (label: string, entries: string[]) =>
        entries.length > 0 && (
            <div className="flex flex-wrap items-center gap-1.5">
                <span className="cc-label mr-1">{label}</span>
                {entries.map((entry) => (
                    <Address key={entry} address={entry} />
                ))}
            </div>
        );

    const reasonFor = (address: string) =>
        accepts(rules, address)
            ? undefined
            : address === people.blocked
              ? t('on the blacklist')
              : t('outside the rules');

    const senders = [people.asker, people.colleague, people.outsider];

    if (people.blocked !== null) {
        senders.push(people.blocked);
    }

    return (
        <section className="flex flex-col gap-4">
            <SectionTitle
                number={4}
                title={t('Whose mail Howy reads')}
                intro={<WithHowy text={intro} />}
            />
            {rules.policy === 'whitelist'
                ? list(t('Whitelist'), rules.whitelist)
                : list(t('Blacklist'), rules.blacklist)}
            <ul className="flex flex-col divide-y divide-cc-border rounded-xl border border-cc-border">
                {[...new Set(senders)].map((address) => (
                    <li
                        key={address}
                        className="flex flex-wrap items-center justify-between gap-2 px-3.5 py-2.5"
                    >
                        <Address address={address} />
                        <Verdict
                            read={accepts(rules, address)}
                            reason={reasonFor(address)}
                        />
                    </li>
                ))}
                <li className="flex flex-wrap items-center justify-between gap-2 px-3.5 py-2.5">
                    <Address address={people.newsletter} />
                    <Verdict read={false} reason={t('automatic mail')} />
                </li>
            </ul>
            <p className="cc-caption">
                <WithHowy
                    text={t(
                        'Mail from outside the rules is still stored in Howy and marked as ignored, so you can see it arrived and why nothing followed.',
                    )}
                />
            </p>
        </section>
    );
}

function WhoGetsReply({
    rules,
    people,
    t,
}: {
    rules: MailboxRules;
    people: People;
    t: Translate;
}) {
    const others = [people.colleague, people.outsider];

    if (people.blocked !== null) {
        others.push(people.blocked);
    }

    const copied = [...new Set(others)];
    const sends = maySendTo(rules, people.asker);

    return (
        <section className="flex flex-col gap-4">
            <SectionTitle
                number={5}
                title={t('Who gets a reply')}
                intro={
                    <WithHowy
                        text={t(
                            'Howy only replies when it is in To. The reply goes to the sender, or to the Reply-To address if the sender set one. Everyone else on the mail is copied in, but only those the rules let in.',
                        )}
                    />
                }
            />
            <div className="grid items-center gap-3 md:grid-cols-[1fr_auto_1fr]">
                <div className="flex flex-col gap-1.5">
                    <span className="cc-label">{t('Incoming mail')}</span>
                    <MailCard
                        from={people.asker}
                        to={[rules.address, people.colleague]}
                        cc={copied.filter((a) => a !== people.colleague)}
                        subject={t('Delivery times in December?')}
                        mailbox={rules.address}
                        t={t}
                    />
                </div>
                <div className="flex justify-center text-cc-subtle">
                    <ArrowRight className="hidden size-5 md:block" />
                    <ArrowDown className="size-5 md:hidden" />
                </div>
                <div className="flex flex-col gap-1.5">
                    <span className="cc-label">
                        <WithHowy text={t("Howy's reply")} />
                    </span>
                    {sends ? (
                        <div className="flex flex-col gap-1.5 rounded-xl border-[1.5px] border-cc-accent-deep/30 bg-cc-wash p-3">
                            <ReplyRow label={t('From:')}>
                                <Address
                                    address={rules.address}
                                    tone="mailbox"
                                />
                            </ReplyRow>
                            <ReplyRow label={t('To:')}>
                                <Address address={people.asker} />
                            </ReplyRow>
                            <ReplyRow label={t('CC:')}>
                                {copied.map((address) => (
                                    <Address
                                        key={address}
                                        address={address}
                                        tone={
                                            maySendTo(rules, address)
                                                ? 'person'
                                                : 'left-out'
                                        }
                                    />
                                ))}
                            </ReplyRow>
                            {copied.some(
                                (address) => !maySendTo(rules, address),
                            ) && (
                                <p className="cc-caption mt-1">
                                    <WithHowy
                                        text={t(
                                            'Struck through: left off, because the rules do not let them in.',
                                        )}
                                    />
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="flex gap-2.5 rounded-xl bg-cc-noise-bg p-3.5 text-[13px] leading-[1.5] text-cc-noise-fg">
                            <Ban className="mt-0.5 size-4 shrink-0" />
                            <span>
                                <WithHowy
                                    text={
                                        rules.repliesEnabled
                                            ? t(
                                                  'No reply: :sender is outside the rules, so Howy does not read the mail at all, and nobody else gets anything either.',
                                                  { sender: people.asker },
                                              )
                                            : t(
                                                  'Replies are off: Howy sends nothing, to anyone. The answer is kept in Howy.',
                                              )
                                    }
                                />
                            </span>
                        </div>
                    )}
                </div>
            </div>
            <ul className="flex flex-col gap-2">
                <Point icon={Send}>
                    {t(
                        'In the same thread, from :address, with "Re:" before the subject.',
                        { address: rules.address },
                    )}
                </Point>
                <Point icon={ScrollText}>
                    {t(
                        'With its sources by name: document, section and page, or the subject of a mail. No links into the knowledge base, no files.',
                    )}
                </Point>
                <Point icon={Repeat}>
                    <WithHowy
                        text={t(
                            'Marked as an automatic reply, so other systems do not answer it, and Howy never answers itself. Each mail is answered at most once.',
                        )}
                    />
                </Point>
                <Point icon={Mail}>
                    {t(
                        'Gmail keeps the reply in the Sent folder of the mailbox.',
                    )}
                </Point>
            </ul>
        </section>
    );
}

function ReplyRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex min-w-0 items-start gap-2">
            <span className="w-[42px] shrink-0 pt-0.5 text-[11px] font-semibold tracking-[0.06em] text-cc-faint uppercase">
                {label}
            </span>
            <div className="flex min-w-0 flex-wrap gap-1">{children}</div>
        </div>
    );
}

type Scenario = {
    title: string;
    from: string;
    to: string[];
    cc?: string[];
    subject: string;
    body: string;
    tone: 'sent' | 'silent' | 'ignored';
    outcome: string;
};

function Examples({
    rules,
    people,
    percent,
    t,
}: {
    rules: MailboxRules;
    people: People;
    percent: number;
    t: Translate;
}) {
    const ignored = (sender: string): Pick<Scenario, 'tone' | 'outcome'> => ({
        tone: 'ignored',
        outcome: t(
            ':sender is outside the rules: the mail is stored in Howy, but not read and not answered.',
            { sender },
        ),
    });

    const answered = (sender: string): Pick<Scenario, 'tone' | 'outcome'> =>
        !accepts(rules, sender)
            ? ignored(sender)
            : rules.repliesEnabled
              ? {
                    tone: 'sent',
                    outcome: t(
                        'Howy replies to :sender in the same thread, with the answer and its sources, if it is at least :percent% sure. Otherwise the answer waits in Howy for a colleague.',
                        { sender, percent },
                    ),
                }
              : {
                    tone: 'silent',
                    outcome: t(
                        'Howy finds the answer and keeps it in Howy. Replies are off, so :sender gets nothing.',
                        { sender },
                    ),
                };

    const scenarios: Scenario[] = [
        {
            title: t('A question to Howy'),
            from: people.asker,
            to: [rules.address],
            subject: t('Opening hours over the holidays?'),
            body: t(
                'What are the opening hours of the service desk over the holidays?',
            ),
            ...answered(people.asker),
        },
        {
            title: t('Howy in CC on news'),
            from: people.asker,
            to: [people.outsider],
            cc: [rules.address],
            subject: t('New rates'),
            body: t('From 1 January our hourly rate is €95.'),
            ...(accepts(rules, people.asker)
                ? {
                      tone: 'silent',
                      outcome: t(
                          'Howy says nothing and writes to no one. The new rate is proposed for the knowledge base, and only counts once someone approves it.',
                      ),
                  }
                : ignored(people.asker)),
        },
        {
            title: t('Someone from outside asks Howy'),
            from: people.outsider,
            to: [rules.address],
            subject: t('Student discount?'),
            body: t('Do you offer a discount for students?'),
            ...answered(people.outsider),
        },
        {
            title: t('A request to write, to Howy alone'),
            from: people.asker,
            to: [rules.address],
            subject: t('Text for the website'),
            body: t(
                'Write a short paragraph about how we handle personal data.',
            ),
            ...(accepts(rules, people.asker) && rules.repliesEnabled
                ? {
                      tone: 'sent',
                      outcome: t(
                          'Written to Howy alone, a request to write counts as a question: Howy writes the paragraph from the approved knowledge and replies to :sender.',
                          { sender: people.asker },
                      ),
                  }
                : answered(people.asker)),
        },
        {
            title: t('A newsletter'),
            from: people.newsletter,
            to: [rules.address],
            subject: t('Our autumn deals'),
            body: t('Only this week: 20% off everything.'),
            tone: 'ignored',
            outcome: t(
                'Automatic mail: stored in Howy, but never read or answered.',
            ),
        },
    ];

    const look = {
        sent: {
            icon: Send,
            className: 'bg-cc-decision-bg text-cc-decision-fg',
        },
        silent: {
            icon: Ear,
            className: 'bg-cc-knowledge-bg text-cc-knowledge-fg',
        },
        ignored: {
            icon: Ban,
            className: 'bg-cc-noise-bg text-cc-noise-fg',
        },
    };

    return (
        <section className="flex flex-col gap-4">
            <SectionTitle
                number={6}
                title={t('Examples')}
                intro={t('Worked out from the rules of this mailbox.')}
            />
            <ul className="grid gap-3 md:grid-cols-2">
                {scenarios.map((scenario) => {
                    const { icon: Icon, className } = look[scenario.tone];

                    return (
                        <li
                            key={scenario.title}
                            className="flex flex-col gap-2.5 rounded-2xl bg-cc-bg p-3.5"
                        >
                            <p className="text-[14px] font-semibold">
                                <WithHowy text={scenario.title} />
                            </p>
                            <MailCard
                                from={scenario.from}
                                to={scenario.to}
                                cc={scenario.cc}
                                subject={scenario.subject}
                                body={scenario.body}
                                mailbox={rules.address}
                                t={t}
                            />
                            <p
                                className={cn(
                                    'flex gap-2 rounded-xl px-3 py-2.5 text-[13px] leading-[1.5]',
                                    className,
                                )}
                            >
                                <Icon className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    <WithHowy text={scenario.outcome} />
                                </span>
                            </p>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

function UnderTheHood({
    company,
    facts,
    t,
}: {
    company: string;
    facts: MailFacts;
    t: Translate;
}) {
    const items: { icon: LucideIcon; title: string; text: string }[] = [
        {
            icon: KeyRound,
            title: t('Access to Gmail'),
            text: t(
                'Read and send, nothing else. Howy cannot change, move, label, mark as read or delete anything in the mailbox.',
            ),
        },
        {
            icon: Database,
            title: t('Stored in Howy'),
            text: t(
                'Every mail that arrives, for the members of :company. Attachments are only downloaded from mail Howy reads.',
                { company },
            ),
        },
        {
            icon: Sparkles,
            title: t('What the AI provider sees'),
            text: t(
                'Only mail Howy reads: the subject and text, quoted earlier messages Howy has not read yet included, and the attachments it reads. Not the sender or the recipients, and email addresses in the text are masked.',
            ),
        },
        {
            icon: ScrollText,
            title: t('What is kept of that'),
            text:
                facts.aiLogDays > 0
                    ? t(
                          'What went to the AI provider is kept encrypted for :days days, so problems can be looked into. After that only how much was used remains.',
                          { days: facts.aiLogDays },
                      )
                    : t(
                          'What went to the AI provider is not kept, only how much was used.',
                      ),
        },
        {
            icon: UserCheck,
            title: t('Approval first'),
            text: t(
                'Facts and documents from mail are proposals. Howy only uses them in answers once someone approves them.',
            ),
        },
    ];

    return (
        <section className="flex flex-col gap-4">
            <SectionTitle number={7} title={t('Under the hood')} />
            <ul className="flex flex-col divide-y divide-cc-border rounded-xl border border-cc-border">
                {items.map(({ icon: Icon, title, text }) => (
                    <li key={title} className="flex gap-3 px-4 py-3.5">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-cc-raised text-cc-ink">
                            <Icon className="size-4" />
                        </span>
                        <div className="flex flex-col gap-0.5">
                            <p className="text-[14px] font-semibold">{title}</p>
                            <p className="text-[13px] leading-[1.5] text-cc-subtle">
                                <WithHowy text={text} />
                            </p>
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
