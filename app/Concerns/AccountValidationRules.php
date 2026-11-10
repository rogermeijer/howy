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
     * The domain the company mails from. Never one anyone can have an address
     * on: howy@ there would not be the company's own.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function domainRules(): array
    {
        return [
            'nullable',
            'string',
            'max:253',
            'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/',
            Rule::notIn(config()->array('mailboxes.public_domains')),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function domainMessages(): array
    {
        return [
            'domain.regex' => __('Enter a domain such as yourcompany.com.'),
            'domain.not_in' => __('Use the domain of your company, not one anyone can have an address on.'),
        ];
    }

    /**
     * A domain as people type it, as it is stored: lower case, without an @,
     * a scheme or a path. Empty is none.
     */
    protected function normalizeDomain(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $domain = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', strtolower(trim($value)));
        $domain = explode('/', ltrim($domain, '@'))[0];

        return $domain === '' ? null : $domain;
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
