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
     * The addresses and domains to set, normalised and validated.
     *
     * @return list<array{entry: string, access: string, creates_colleagues?: bool}>
     */
    public function senders(): array
    {
        /** @var list<array{entry: string, access: string, creates_colleagues?: bool|string}> $senders */
        $senders = $this->validated('sender_rules');

        return array_map(fn (array $sender): array => array_key_exists('creates_colleagues', $sender)
            ? [...$sender, 'creates_colleagues' => filter_var($sender['creates_colleagues'], FILTER_VALIDATE_BOOLEAN)]
            : $sender, $senders);
    }

    /**
     * The addresses and domains to remove.
     *
     * @return list<string>
     */
    public function removed(): array
    {
        /** @var list<string> */
        return $this->validated('removed_rules');
    }

    /**
     * An empty list posts nothing at all, so a missing list is an empty one.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sender_rules' => $this->normalizeSenderRules($this->input('sender_rules')),
            'removed_rules' => $this->normalizeEntries($this->input('removed_rules')),
        ]);
    }
}
