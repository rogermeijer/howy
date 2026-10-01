<?php

namespace App\Http\Requests\Mailboxes;

use Illuminate\Foundation\Http\FormRequest;

class MailboxImportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_ids' => ['required', 'array', 'min:1', 'max:500'],
            'message_ids.*' => ['required', 'string', 'regex:/^[A-Za-z0-9]+$/', 'max:64'],
            'whole_threads' => ['boolean'],
        ];
    }
}
