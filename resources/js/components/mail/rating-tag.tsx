import { Tag } from '@/components/cc/tag';
import type { AnswerRating } from '@/types';

const KINDS = {
    valuable: 'decision',
    partly: 'pending',
    outdated: 'action',
} as const;

/**
 * How whoever asked rated Howy's answer, as a tag in its colour.
 */
export function RatingTag({
    rating,
    label,
    className,
}: {
    rating: AnswerRating;
    label: string;
    className?: string;
}) {
    return <Tag kind={KINDS[rating]} label={label} className={className} />;
}
