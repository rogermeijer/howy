<?php

namespace App\Http\Requests\Mailboxes;

use App\Concerns\MailboxValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class MailboxRulesRequest extends FormRequest
{
    use MailboxValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->senderRules();
    }

    /**
     * The senders to list, normalised and validated.
     *
     * @return list<array{entry: string, access: string}>
     */
    public function senders(): array
    {
        /** @var list<array{entry: string, access: string}> $senders */
        $senders = $this->validated('sender_rules');

        return $senders;
    }

    /**
     * An empty list posts nothing at all, so a missing list is an empty one.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['sender_rules' => $this->normalizeSenderRules($this->input('sender_rules'))]);
    }
}
