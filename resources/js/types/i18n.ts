export type LocaleOption = {
    value: string;
    label: string;
};

/**
 * Props every page receives for rendering text and dates.
 *
 * `translations` is empty for English: keys are the English source strings, so
 * t() falls through to the key and renders correctly with no catalogue.
 */
export type LocalizationProps = {
    locale: string;
    locales: LocaleOption[];
    timezone: string;
    translations: Record<string, string>;
};
