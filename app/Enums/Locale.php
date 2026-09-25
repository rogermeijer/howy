<?php

namespace App\Enums;

/**
 * The locales the application ships translations for.
 *
 * Adding one means adding a case here and a lang/{code}.json file, nothing else.
 * English has no catalogue: translation keys are the English source strings.
 */
enum Locale: string
{
    case English = 'en';
    case Dutch = 'nl';

    /**
     * The language's name in its own language, which is the convention for a
     * language switcher: someone who cannot read the current UI can still find
     * their own language in the list.
     */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Dutch => 'Nederlands',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
