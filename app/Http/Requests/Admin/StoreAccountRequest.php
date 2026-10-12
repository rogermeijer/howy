<?php

namespace App\Http\Requests\Admin;

use App\Concerns\AccountValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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
            'owner_email' => ['required', 'string', 'email', Rule::exists(User::class, 'email')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'owner_email.exists' => __('There is no user with this email address. Create the user first.'),
        ];
    }
}
