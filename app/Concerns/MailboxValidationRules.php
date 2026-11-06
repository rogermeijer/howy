<?php

namespace App\Concerns;

use App\Enums\SenderAccess;
use App\Models\Mailbox;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait MailboxValidationRules
{
    /** A domain such as noordkade.nl, optionally written as @noordkade.nl. */
    private const string DOMAIN_PATTERN = '/^@?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';

    /**
     * What Howy may do with mail on a mailbox: an access for its own domain,
     * and per address or domain on top; everyone else is ignored. Normalise the
     * rules with normalizeSenderRules() before validating. A public domain
     * such as gmail.com is never a rule: it would let in everyone on it, so
     * its addresses are listed one by one.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Closure>>
     */
    protected function senderRules(): array
    {
        $entry = function (string $attribute, mixed $value, Closure $fail): void {
            $value = is_string($value) ? $value : '';

            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false && preg_match(self::DOMAIN_PATTERN, $value) !== 1) {
                $fail(__(':value is not an email address or a domain.', ['value' => $value]));
            } elseif (! str_contains($value, '@') && Mailbox::isPublicDomain($value)) {
                $fail(__(':value is a public domain: add the addresses one by one.', ['value' => $value]));
            }
        };

        return [
            'domain_access' => ['required', Rule::enum(SenderAccess::class)],
            'sender_rules' => ['present', 'array', 'max:500'],
            'sender_rules.*' => ['array:entry,access'],
            'sender_rules.*.entry' => ['required', 'string', 'max:255', $entry],
            'sender_rules.*.access' => ['required', Rule::enum(SenderAccess::class)],
        ];
    }

    /**
     * Trimmed and lowercased, a domain without its @, and every address or
     * domain once: a later rule for it replaces an earlier one, so a pasted
     * list can move senders that are already there.
     *
     * @return list<array{entry: string, access: mixed}>
     */
    protected function normalizeSenderRules(mixed $value): array
    {
        $rules = [];

        foreach (is_array($value) ? $value : [] as $rule) {
            $entry = is_array($rule) && is_string($rule['entry'] ?? null) ? strtolower(trim($rule['entry'])) : '';
            $entry = str_contains(ltrim($entry, '@'), '@') ? $entry : ltrim($entry, '@');

            if ($entry === '') {
                continue;
            }

            unset($rules[$entry]);
            $rules[$entry] = ['entry' => $entry, 'access' => is_array($rule) ? ($rule['access'] ?? null) : null];
        }

        return array_values($rules);
    }
}
