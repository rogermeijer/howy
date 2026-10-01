<?php

namespace App\Http\Requests\Mailboxes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailboxSearchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:500'],
            'range' => ['nullable', Rule::in(['7d', '30d'])],
            'attachments' => ['nullable', 'boolean'],
            'page_token' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Combine the free text with the chips into one Gmail search.
     */
    public function gmailQuery(): string
    {
        return trim(implode(' ', array_filter([
            $this->string('q')->trim()->toString(),
            match ($this->input('range')) {
                '7d' => 'newer_than:7d',
                '30d' => 'newer_than:30d',
                default => null,
            },
            $this->boolean('attachments') ? 'has:attachment' : null,
        ])));
    }
}
