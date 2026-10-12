<?php

namespace App\Http\Requests\Admin;

use App\Concerns\AccountValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use AccountValidationRules, ProfileValidationRules;

    /**
     * "Follow the account" travels as the same marker the profile form uses.
     */
    protected function prepareForValidation(): void
    {
        if (in_array($this->input('locale'), ['', ProfileUpdateRequest::INHERIT], true)) {
            $this->merge(['locale' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'locale' => $this->localeRules(required: false),
            'account_id' => ['nullable', 'integer', Rule::exists(Account::class, 'id')],
            'account_admin' => ['boolean'],
            'is_super_admin' => ['boolean'],
        ];
    }
}
