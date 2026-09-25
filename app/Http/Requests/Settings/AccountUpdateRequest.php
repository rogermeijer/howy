<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AccountValidationRules;
use App\Facades\Tenancy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountUpdateRequest extends FormRequest
{
    use AccountValidationRules;

    /**
     * Only an administrator of the current account may change its settings.
     */
    public function authorize(): bool
    {
        return Tenancy::check() && $this->user()->isAdminOf(Tenancy::account());
    }

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
