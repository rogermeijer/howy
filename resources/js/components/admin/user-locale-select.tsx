import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import type { LocaleOption } from '@/types';

/** Matches ProfileUpdateRequest::INHERIT on the server. */
const inherit = '__inherit__';

/**
 * A user's language, where "follow the account" stores null.
 */
export function UserLocaleSelect({
    locales,
    value,
    error,
}: {
    locales: LocaleOption[];
    value: string | null;
    error?: string;
}) {
    const t = useTranslations();

    return (
        <div className="grid gap-2">
            <Label htmlFor="locale">{t('Language')}</Label>
            <Select name="locale" defaultValue={value ?? inherit}>
                <SelectTrigger id="locale" className="h-11 w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={inherit}>
                        {t('Follow account')}
                    </SelectItem>
                    {locales.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
