import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { LocalizationProps } from '@/types';

/**
 * Format a server timestamp in the viewer's locale and timezone.
 *
 * Timestamps are stored and sent as UTC; converting happens here, at the display
 * boundary, rather than by shifting the application's timezone — which would make
 * Eloquent write local times into UTC columns.
 */
export function useFormatDate() {
    const { locale, timezone } = usePage<LocalizationProps>().props;

    return useCallback(
        (value: string | Date, options: Intl.DateTimeFormatOptions = {}) =>
            new Intl.DateTimeFormat(locale, {
                dateStyle: 'medium',
                timeZone: timezone,
                ...options,
            }).format(typeof value === 'string' ? new Date(value) : value),
        [locale, timezone],
    );
}
