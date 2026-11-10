<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AccountValidationRules;
use App\Facades\Tenancy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The account's name, domain, language and timezone: from the settings page,
 * and from the first step of setup.
 */
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
            'domain' => $this->domainRules(),
            'locale' => $this->localeRules(),
            'timezone' => $this->timezoneRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->domainMessages();
    }

    /**
     * A form without the field leaves the domain as it is.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('domain')) {
            $this->merge(['domain' => $this->normalizeDomain($this->input('domain'))]);
        }
    }
}
