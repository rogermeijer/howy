<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Removing a document is a choice about its facts, made by whoever removes
 * it: keep them, or remove the ones nothing else states. There is no
 * default.
 */
class DestroyDocumentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'facts' => ['required', Rule::in(['keep', 'remove'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'facts.required' => __('Choose what happens to its facts.'),
        ];
    }

    public function keepsFacts(): bool
    {
        return $this->input('facts') === 'keep';
    }
}
