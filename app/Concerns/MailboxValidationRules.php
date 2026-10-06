<?php

namespace App\Concerns;

use App\Enums\SendPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait MailboxValidationRules
{
    /** A domain such as noordkade.nl, optionally written as @noordkade.nl. */
    private const string DOMAIN_PATTERN = '/^@?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';

    /**
     * Who Howy may reply to from a mailbox. List entries are a full address or
     * a domain; normalise them with normalizeSendList() before validating.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Closure>>
     */
    protected function sendPolicyRules(): array
    {
        $entry = function (string $attribute, mixed $value, Closure $fail): void {
            $value = is_string($value) ? $value : '';

            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false && preg_match(self::DOMAIN_PATTERN, $value) !== 1) {
                $fail(__(':value is not an email address or a domain.', ['value' => $value]));
            }
        };

        return [
            'send_policy' => ['required', Rule::enum(SendPolicy::class)],
            'send_whitelist' => ['present', 'array', 'max:200'],
            'send_whitelist.*' => ['string', 'max:255', $entry],
            'send_blacklist' => ['present', 'array', 'max:200'],
            'send_blacklist.*' => ['string', 'max:255', $entry],
        ];
    }

    /**
     * Trimmed, lowercased, without blanks or duplicates. Accepts a list or the
     * one-per-line text of a textarea.
     *
     * @return list<string>
     */
    protected function normalizeSendList(mixed $value): array
    {
        $entries = is_array($value) ? $value : preg_split('/[\s,;]+/', is_string($value) ? $value : '');

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $entry): string => strtolower(trim(is_string($entry) ? $entry : '')), $entries ?: []),
            fn (string $entry): bool => $entry !== '',
        )));
    }
}
