<?php

namespace App\Http\Requests\Admin;

use App\Concerns\AccountValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->route('user');

        return [
            ...$this->profileRules($user->id),
            'locale' => $this->localeRules(required: false),
            'is_super_admin' => ['boolean'],
        ];
    }
}
