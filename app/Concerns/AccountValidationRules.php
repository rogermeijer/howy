<?php

namespace App\Concerns;

use App\Enums\Locale;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AccountValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function accountRules(): array
    {
        return [
            'account_name' => $this->accountNameRules(),
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function accountNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Validation rules for a locale column.
     *
     * @param  bool  $required  Account locales are required; user locales are
     *                          nullable, where null means "follow the account".
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function localeRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            Rule::enum(Locale::class),
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function timezoneRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            Rule::in(DateTimeZone::listIdentifiers()),
        ];
    }
}
