import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { LocalizationProps } from '@/types';

/**
 * Translate a key, mirroring Laravel's __() helper.
 *
 * Keys are the English source string, so a missing translation renders correct
 * English rather than a raw key — which is what makes a partial sweep safe.
 *
 * Placeholders use Laravel's `:name` syntax:
 *
 *     t('Welcome back, :name', { name: user.name })
 */
export function useTranslations() {
    const { translations } = usePage<LocalizationProps>().props;

    return useCallback(
        (key: string, replacements: Record<string, string | number> = {}) => {
            const line = translations[key] ?? key;

            return Object.entries(replacements).reduce(
                (text, [placeholder, value]) =>
                    text.replace(`:${placeholder}`, String(value)),
                line,
            );
        },
        [translations],
    );
}
