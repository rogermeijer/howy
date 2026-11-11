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
     * and per address or domain on top; everyone else is ignored. A domain may
     * say whether those it lets in become colleagues. Only what changed is
     * sent: the rules set, and the addresses and domains removed. Normalise
     * both with normalizeSenderRules() and normalizeEntries() before
     * validating. A public domain such as gmail.com is never a rule: it would
     * let in everyone on it, so its addresses are listed one by one.
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
            'sender_rules.*' => ['array:entry,access,creates_colleagues'],
            'sender_rules.*.entry' => ['required', 'string', 'max:255', $entry],
            'sender_rules.*.access' => ['required', Rule::enum(SenderAccess::class)],
            'sender_rules.*.creates_colleagues' => ['sometimes', 'boolean'],
            'removed_rules' => ['present', 'array', 'max:500'],
            'removed_rules.*' => ['string', 'max:255'],
            // Welcome the colleagues an address on the list makes, when the account is live.
            'welcome_new' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Trimmed and lowercased, a domain without its @, and every address or
     * domain once: a later rule for it replaces an earlier one, so a pasted
     * list can move senders that are already there.
     *
     * @return list<array<string, mixed>>
     */
    protected function normalizeSenderRules(mixed $value): array
    {
        $rules = [];

        foreach (is_array($value) ? $value : [] as $rule) {
            $entry = is_array($rule) ? $this->normalizeEntry($rule['entry'] ?? null) : '';

            if ($entry === '') {
                continue;
            }

            unset($rules[$entry]);
            $rules[$entry] = ['entry' => $entry, ...array_diff_key($rule, ['entry' => true])];
        }

        return array_values($rules);
    }

    /**
     * Addresses and domains, written the way rules are.
     *
     * @return list<string>
     */
    protected function normalizeEntries(mixed $value): array
    {
        $entries = array_map(fn (mixed $entry): string => $this->normalizeEntry($entry), is_array($value) ? $value : []);

        return array_values(array_unique(array_filter($entries, fn (string $entry): bool => $entry !== '')));
    }

    private function normalizeEntry(mixed $value): string
    {
        $entry = is_string($value) ? strtolower(trim($value)) : '';

        return str_contains(ltrim($entry, '@'), '@') ? $entry : ltrim($entry, '@');
    }
}
