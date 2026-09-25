import InputError from '@/components/input-error';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import type { LocaleOption } from '@/types';

type Props = {
    locales: LocaleOption[];
    timezones: string[];
    locale: string | null;
    timezone: string | null;
    /** Offers an empty "follow the account" choice, for user-level preferences. */
    inheritable?: boolean;
    errors: Partial<Record<string, string>>;
};

/**
 * Language and timezone selects, shared by the profile and account forms.
 *
 * On the profile they are inheritable: an empty value stores null, which makes
 * the user follow their account. That is the fallback made visible rather than
 * hidden behind a blank field.
 */
export function LocaleFields({
    locales,
    timezones,
    locale,
    timezone,
    inheritable = false,
    errors,
}: Props) {
    const t = useTranslations();
    // Matches ProfileUpdateRequest::INHERIT — a Radix select cannot use an
    // empty string as a value, so the inherit choice travels as this marker.
    const inherit = '__inherit__';

    return (
        <div className="grid gap-5 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="locale">{t('Language')}</Label>
                <Select
                    name="locale"
                    defaultValue={locale ?? (inheritable ? inherit : undefined)}
                >
                    <SelectTrigger id="locale" className="h-11">
                        <SelectValue placeholder={t('Choose a language')} />
                    </SelectTrigger>
                    <SelectContent>
                        {inheritable && (
                            <SelectItem value={inherit}>
                                {t('Follow account')}
                            </SelectItem>
                        )}
                        {locales.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.locale} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="timezone">{t('Timezone')}</Label>
                <Select
                    name="timezone"
                    defaultValue={
                        timezone ?? (inheritable ? inherit : undefined)
                    }
                >
                    <SelectTrigger id="timezone" className="h-11">
                        <SelectValue placeholder={t('Choose a timezone')} />
                    </SelectTrigger>
                    <SelectContent>
                        {inheritable && (
                            <SelectItem value={inherit}>
                                {t('Follow account')}
                            </SelectItem>
                        )}
                        {timezones.map((zone) => (
                            <SelectItem key={zone} value={zone}>
                                {zone}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.timezone} />
            </div>
        </div>
    );
}
