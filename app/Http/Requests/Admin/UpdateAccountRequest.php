<?php

namespace App\Http\Requests\Admin;

use App\Concerns\AccountValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    use AccountValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->accountNameRules(),
            'locale' => $this->localeRules(),
            'timezone' => $this->timezoneRules(),
        ];
    }
}
