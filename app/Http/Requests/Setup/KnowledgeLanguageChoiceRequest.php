<?php

namespace App\Http\Requests\Setup;

use App\Http\Requests\Settings\KnowledgeLanguageUpdateRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The knowledge base language, chosen during setup. Changing it later lowers
 * the quality of the knowledge base, so the choice comes with an explicit
 * acknowledgement that it is meant to stay.
 */
class KnowledgeLanguageChoiceRequest extends KnowledgeLanguageUpdateRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'understood' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'understood.accepted' => __('Confirm that the knowledge base stays in this language.'),
        ];
    }
}
