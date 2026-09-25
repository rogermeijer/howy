<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AccountValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use AccountValidationRules, ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            // Nullable on purpose: an empty value means "follow the account".
            'locale' => $this->localeRules(required: false),
            'timezone' => $this->timezoneRules(required: false),
        ];
    }

    /**
     * The sentinel the "follow account" option submits.
     *
     * A Radix select cannot carry an empty string as a value, so the inherit
     * choice travels as this marker and is stored as null.
     */
    public const string INHERIT = '__inherit__';

    protected function prepareForValidation(): void
    {
        foreach (['locale', 'timezone'] as $field) {
            if (in_array($this->input($field), ['', self::INHERIT], true)) {
                $this->merge([$field => null]);
            }
        }
    }
}
