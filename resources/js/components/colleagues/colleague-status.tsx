import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { ColleagueStatus } from '@/types';

/** What each status is called, and its colour. */
export function useStatusLooks(): Record<
    ColleagueStatus,
    { label: string; tag: string }
> {
    const t = useTranslations();

    return {
        not_welcomed: { label: t('Not welcomed yet'), tag: 'cc-tag-pending' },
        welcomed: { label: t('Welcomed'), tag: 'cc-tag-decision' },
        ignored: { label: t('Ignored'), tag: 'cc-tag-noise' },
        no_mailbox: { label: t('No mailbox'), tag: 'cc-tag-pending' },
        opted_out: { label: t('Unsubscribed'), tag: 'cc-tag-noise' },
        off: { label: t('Howy mails off'), tag: 'cc-tag-noise' },
    };
}

/** Where someone's Howy mails stand, as a tag. */
export function ColleagueStatusTag({
    status,
    className,
}: {
    status: ColleagueStatus;
    className?: string;
}) {
    const look = useStatusLooks()[status];

    return (
        <span className={cn('cc-tag', look.tag, className)}>{look.label}</span>
    );
}

/**
 * What someone did, in one line: their mails, the questions among them, and
 * those that added to the knowledge base.
 */
export function useActivityLine() {
    const t = useTranslations();

    return (counts: {
        emailsCount: number;
        questionsCount: number;
        contributionsCount: number;
    }) =>
        counts.emailsCount === 0
            ? t('No mail yet')
            : [
                  counts.emailsCount === 1
                      ? t('1 mail')
                      : t(':count mails', { count: counts.emailsCount }),
                  counts.questionsCount === 1
                      ? t('1 question')
                      : t(':count questions', { count: counts.questionsCount }),
                  counts.contributionsCount === 1
                      ? t('1 contribution')
                      : t(':count contributions', {
                            count: counts.contributionsCount,
                        }),
              ].join(' · ');
}
