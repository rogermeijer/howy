import { Form, Link } from '@inertiajs/react';
import { Moon, Send, Sun } from 'lucide-react';
import { useState } from 'react';
import { AdminShell } from '@/components/admin/admin-shell';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index, send } from '@/routes/admin/mails';
import type {
    LocaleOption,
    MailPreviewAccess,
    MailPreviewChoice,
    MailPreviewGroup,
    MailPreviewItem,
} from '@/types';

type Props = {
    mails: MailPreviewItem[];
    selected: MailPreviewChoice;
    preview: { subject: string; html: string; text: string } | null;
    locales: LocaleOption[];
};

const groups: MailPreviewGroup[] = ['flow', 'reply', 'system', 'parked'];

/**
 * Every mail Howy sends, and the ones parked for later, with sample data:
 * per language, per kind of access, light or dark, as HTML or as plain text.
 */
export default function MailPreviews({
    mails,
    selected,
    preview,
    locales,
}: Props) {
    const t = useTranslations();
    const [theme, setTheme] = useState<'light' | 'dark'>('light');
    const [view, setView] = useState<'html' | 'text'>('html');

    const current = mails.find((mail) => mail.key === selected.mail);

    const groupLabel: Record<MailPreviewGroup, string> = {
        flow: t('Flow mails'),
        reply: t('Replies'),
        system: t('Account mails'),
        parked: t('Parked'),
    };

    const accessLabel: Record<MailPreviewAccess, string> = {
        learn_answer: t('Learn and answer'),
        learn: t('Learn only'),
        answer: t('Answer only'),
    };

    const href = (choice: Partial<MailPreviewChoice>) =>
        index({ query: { ...selected, ...choice } });

    return (
        <AdminShell
            title={t('Mails')}
            description={t(
                'Every mail Howy sends, with sample data. Parked mails are designed but not sent yet.',
            )}
        >
            <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <nav aria-label={t('Mails')} className="flex flex-col gap-5">
                    {groups.map((group) => (
                        <div key={group} className="flex flex-col gap-1">
                            <span className="cc-label mb-1">
                                {groupLabel[group]}
                            </span>
                            {mails
                                .filter((mail) => mail.group === group)
                                .map((mail) => (
                                    <Link
                                        key={mail.key}
                                        href={href({ mail: mail.key })}
                                        preserveScroll
                                        className={cn(
                                            'rounded-lg px-3 py-2 text-sm font-medium text-cc-muted hover:bg-cc-raised hover:text-cc-ink',
                                            mail.key === selected.mail &&
                                                'bg-cc-ink text-white hover:bg-cc-ink hover:text-white',
                                        )}
                                    >
                                        {mail.title}
                                    </Link>
                                ))}
                        </div>
                    ))}
                </nav>

                <div className="flex min-w-0 flex-col gap-4">
                    <div className="flex flex-wrap items-center gap-2">
                        {locales.map((locale) => (
                            <Link
                                key={locale.value}
                                href={href({ locale: locale.value })}
                                preserveScroll
                                className={cn(
                                    'cc-pill inline-flex items-center',
                                    selected.locale === locale.value &&
                                        'cc-pill-active',
                                )}
                            >
                                {locale.label}
                            </Link>
                        ))}

                        {current?.access && (
                            <>
                                <span className="mx-1 h-5 w-px bg-cc-border" />
                                {(
                                    Object.keys(
                                        accessLabel,
                                    ) as MailPreviewAccess[]
                                ).map((access) => (
                                    <Link
                                        key={access}
                                        href={href({ access })}
                                        preserveScroll
                                        className={cn(
                                            'cc-pill inline-flex items-center',
                                            selected.access === access &&
                                                'cc-pill-active',
                                        )}
                                    >
                                        {accessLabel[access]}
                                    </Link>
                                ))}
                            </>
                        )}

                        <span className="mx-1 h-5 w-px bg-cc-border" />
                        <button
                            type="button"
                            onClick={() =>
                                setTheme(theme === 'light' ? 'dark' : 'light')
                            }
                            className="cc-pill inline-flex items-center gap-1.5"
                        >
                            {theme === 'light' ? (
                                <Sun className="size-4" />
                            ) : (
                                <Moon className="size-4" />
                            )}
                            {theme === 'light' ? t('Light') : t('Dark')}
                        </button>
                        <button
                            type="button"
                            onClick={() =>
                                setView(view === 'html' ? 'text' : 'html')
                            }
                            className="cc-pill inline-flex items-center"
                        >
                            {view === 'html'
                                ? t('Show plain text')
                                : t('Show design')}
                        </button>

                        <Form
                            {...send.form()}
                            options={{ preserveScroll: true }}
                            className="ml-auto"
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="mail"
                                        value={selected.mail}
                                    />
                                    <input
                                        type="hidden"
                                        name="locale"
                                        value={selected.locale}
                                    />
                                    <input
                                        type="hidden"
                                        name="access"
                                        value={selected.access}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="cc-btn-accent"
                                    >
                                        <Send className="size-4" />
                                        {t('Send test to me')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>

                    {preview && (
                        <div className="cc-panel flex flex-col overflow-hidden">
                            <div className="flex flex-col gap-0.5 border-b border-cc-border px-5 py-3">
                                <span className="cc-label">{t('Subject')}</span>
                                <span className="text-[15px] font-semibold text-cc-ink">
                                    {preview.subject}
                                </span>
                            </div>
                            {view === 'html' ? (
                                <iframe
                                    title={preview.subject}
                                    srcDoc={preview.html}
                                    sandbox=""
                                    style={{ colorScheme: theme }}
                                    className="h-[75vh] w-full border-0"
                                />
                            ) : (
                                <pre className="h-[75vh] overflow-auto px-5 py-4 text-[13px] leading-relaxed whitespace-pre-wrap text-cc-ink">
                                    {preview.text}
                                </pre>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
