<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Locale;
use DateTimeZone;

/**
 * The select options the admin forms share.
 */
final class Options
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function locales(): array
    {
        return array_map(
            fn (Locale $locale): array => ['value' => $locale->value, 'label' => $locale->label()],
            Locale::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }
}
