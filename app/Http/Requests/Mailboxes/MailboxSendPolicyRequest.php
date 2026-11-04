<?php

namespace App\Http\Requests\Mailboxes;

use App\Concerns\MailboxValidationRules;
use App\Models\Mailbox;
use Illuminate\Foundation\Http\FormRequest;

class MailboxSendPolicyRequest extends FormRequest
{
    use MailboxValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Mailbox $mailbox */
        $mailbox = $this->route('mailbox');

        return $this->sendPolicyRules($mailbox);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'send_whitelist' => $this->normalizeSendList($this->input('send_whitelist')),
            'send_blacklist' => $this->normalizeSendList($this->input('send_blacklist')),
            'replies_enabled' => $this->boolean('replies_enabled'),
        ]);
    }
}
