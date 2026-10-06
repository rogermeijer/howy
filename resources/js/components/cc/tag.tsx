import { cn } from '@/lib/utils';
import { useTranslations } from '@/hooks/use-translations';

export type InterpretationKind =
    | 'question'
    | 'decision'
    | 'action'
    | 'knowledge'
    | 'noise'
    | 'pending';

const styles: Record<InterpretationKind, string> = {
    question: 'cc-tag-question',
    decision: 'cc-tag-decision',
    action: 'cc-tag-action',
    knowledge: 'cc-tag-knowledge',
    noise: 'cc-tag-noise',
    pending: 'cc-tag-pending',
};

/**
 * English source strings, translated at render time by the Tag component and by
 * anything that builds a filter list from them.
 */
export const interpretationLabels: Record<InterpretationKind, string> = {
    question: 'Question',
    decision: 'Decision',
    action: 'Warning',
    knowledge: 'Knowledge',
    noise: 'Noise',
    pending: 'To confirm',
};

type Props = {
    kind: InterpretationKind;
    label?: string;
    className?: string;
};

export function Tag({ kind, label, className }: Props) {
    const t = useTranslations();
    return (
        <span className={cn('cc-tag', styles[kind], className)}>
            {label ?? t(interpretationLabels[kind])}
        </span>
    );
}
