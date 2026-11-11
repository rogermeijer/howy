<?php

namespace App\Http\Requests\Colleagues;

use App\Concerns\ColleagueValidationRules;
use App\Enums\SenderAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreColleagueRequest extends FormRequest
{
    use ColleagueValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->newColleagueRules();
    }

    /**
     * Their own rule on their home mailbox; null to follow their domain's.
     */
    public function access(): ?SenderAccess
    {
        return SenderAccess::tryFrom($this->string('access')->toString());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim($this->string('email')->toString()))]);
    }
}
