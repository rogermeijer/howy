<?php

namespace App\Concerns;

use App\Enums\SenderAccess;
use App\Models\Colleague;
use App\Models\Mailbox;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ColleagueValidationRules
{
    /**
     * A new colleague: their address, once per account, and the mailbox their
     * Howy mails come from.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Closure>>
     */
    protected function newColleagueRules(): array
    {
        $unique = function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && Colleague::query()->where('email', strtolower(trim($value)))->exists()) {
                $fail(__(':email is a colleague already.', ['email' => strtolower(trim($value))]));
            }
        };

        return [
            'email' => ['required', 'string', 'email', 'max:255', $unique],
            ...$this->colleagueRules(),
            'access' => $this->colleagueAccessRules(),
        ];
    }

    /**
     * What an admin can change about a colleague.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Closure>>
     */
    protected function colleagueRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'home_mailbox_id' => ['required', 'integer', $this->ownMailbox()],
            'receives_mail' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A rule of their own on a mailbox, or "default": none, so the rule for
     * their domain decides.
     *
     * @return array<int, ValidationRule|array<mixed>|string|Closure>
     */
    protected function colleagueAccessRules(): array
    {
        return ['required', Rule::in(['default', ...array_column(SenderAccess::cases(), 'value')])];
    }

    /**
     * One of this account's mailboxes: an id from elsewhere is not one.
     */
    private function ownMailbox(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_numeric($value) || ! Mailbox::query()->whereKey((int) $value)->exists()) {
                $fail(__('Choose one of your mailboxes.'));
            }
        };
    }
}
