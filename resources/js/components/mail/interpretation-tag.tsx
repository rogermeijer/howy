import type { InterpretationKind } from '@/components/cc/tag';
import { Tag } from '@/components/cc/tag';
import { useTranslations } from '@/hooks/use-translations';
import type { InterpretationBrief, InterpretationOutcome } from '@/types';

const outcomeKinds: Record<InterpretationOutcome, InterpretationKind> = {
    answered: 'question',
    not_found: 'pending',
    suggested: 'question',
    unsure: 'noise',
    added: 'knowledge',
    duplicate: 'noise',
    conflict: 'action',
    no_action: 'noise',
};

/**
 * One tag that says where a mail stands: still being read, what came of it,
 * or why it was not read at all.
 */
export function InterpretationTag({
    interpretation,
    className,
}: {
    interpretation: InterpretationBrief | null;
    className?: string;
}) {
    const t = useTranslations();

    if (interpretation === null) {
        return (
            <Tag
                kind="noise"
                label={t('Not processed')}
                className={className}
            />
        );
    }

    const { status, statusLabel, outcome, outcomeLabel } = interpretation;

    if (status === 'done' && outcome !== null) {
        return (
            <Tag
                kind={outcomeKinds[outcome]}
                label={outcomeLabel ?? statusLabel}
                className={className}
            />
        );
    }

    return (
        <Tag
            kind={
                status === 'failed'
                    ? 'action'
                    : status === 'skipped'
                      ? 'noise'
                      : 'pending'
            }
            label={statusLabel}
            className={className}
        />
    );
}
