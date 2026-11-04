<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AccountValidationRules;
use App\Facades\Tenancy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class KnowledgeLanguageUpdateRequest extends FormRequest
{
    use AccountValidationRules;

    /**
     * Only an administrator of the current account chooses its language.
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
            'knowledge_locale' => $this->localeRules(),
        ];
    }
}
