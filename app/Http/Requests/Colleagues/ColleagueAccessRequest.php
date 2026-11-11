<?php

namespace App\Http\Requests\Colleagues;

use App\Concerns\ColleagueValidationRules;
use App\Enums\SenderAccess;
use Illuminate\Foundation\Http\FormRequest;

class ColleagueAccessRequest extends FormRequest
{
    use ColleagueValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['access' => $this->colleagueAccessRules()];
    }

    /**
     * Their own rule; null to follow their domain's.
     */
    public function access(): ?SenderAccess
    {
        return SenderAccess::tryFrom($this->string('access')->toString());
    }
}
