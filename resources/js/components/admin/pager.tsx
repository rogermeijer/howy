import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import type { Paginated } from '@/types';

type Props = {
    page: Pick<Paginated<unknown>, 'prev_page_url' | 'next_page_url'> &
        Partial<Pick<Paginated<unknown>, 'current_page' | 'last_page'>>;
    previousLabel?: string;
    nextLabel?: string;
};

/**
 * Previous/next links under a paginated list. Renders nothing for one page.
 */
export function Pager({ page, previousLabel, nextLabel }: Props) {
    const t = useTranslations();

    if (!page.prev_page_url && !page.next_page_url) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4">
            {page.prev_page_url ? (
                <Button asChild variant="outline" className="h-10">
                    <Link href={page.prev_page_url} preserveScroll>
                        {previousLabel ?? t('Previous')}
                    </Link>
                </Button>
            ) : (
                <span />
            )}
            {page.current_page !== undefined &&
                page.last_page !== undefined && (
                    <span className="cc-caption">
                        {t('Page :current of :last', {
                            current: page.current_page,
                            last: page.last_page,
                        })}
                    </span>
                )}
            {page.next_page_url ? (
                <Button asChild variant="outline" className="h-10">
                    <Link href={page.next_page_url} preserveScroll>
                        {nextLabel ?? t('Next')}
                    </Link>
                </Button>
            ) : (
                <span />
            )}
        </div>
    );
}
