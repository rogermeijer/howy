<?php

namespace App\Http\Requests\Mailboxes;

use App\Concerns\MailboxValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class MailboxSendPolicyRequest extends FormRequest
{
    use MailboxValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sendPolicyRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'send_whitelist' => $this->normalizeSendList($this->input('send_whitelist')),
            'send_blacklist' => $this->normalizeSendList($this->input('send_blacklist')),
        ]);
    }
}
