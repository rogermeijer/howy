<?php

namespace App\Http\Requests\Colleagues;

use App\Concerns\ColleagueValidationRules;
use App\Enums\SenderAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateColleagueRequest extends FormRequest
{
    use ColleagueValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->colleagueChangeRules(),
            ...$this->colleagueAccessPerMailboxRules(),
        ];
    }

    /**
     * What changes on the colleague themselves.
     *
     * @return array<string, mixed>
     */
    public function colleague(): array
    {
        return $this->safe()->except('access');
    }

    /**
     * Per mailbox id, their own rule there, or null to follow their domain's.
     *
     * @return array<int, SenderAccess|null>
     */
    public function accessPerMailbox(): array
    {
        /** @var array<int|string, string> $access */
        $access = $this->validated('access', []);

        $result = [];

        foreach ($access as $mailbox => $value) {
            $result[(int) $mailbox] = SenderAccess::tryFrom($value);
        }

        return $result;
    }
}
