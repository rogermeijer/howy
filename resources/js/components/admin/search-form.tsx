import { Form } from '@inertiajs/react';
import type { RouteFormDefinition } from '@/wayfinder';
import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    action: RouteFormDefinition<'get'>;
    value: string;
    placeholder: string;
};

/**
 * A GET search over a list, keeping the query in the URL.
 */
export function SearchForm({ action, value, placeholder }: Props) {
    const t = useTranslations();

    return (
        <Form
            {...action}
            options={{ preserveState: true }}
            className="w-full sm:max-w-sm"
        >
            <label className="relative block">
                <span className="sr-only">{t('Search')}</span>
                <Search
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-cc-subtle"
                />
                <Input
                    type="search"
                    name="q"
                    defaultValue={value}
                    placeholder={placeholder}
                    className="h-11 bg-cc-panel pl-10"
                />
            </label>
        </Form>
    );
}
