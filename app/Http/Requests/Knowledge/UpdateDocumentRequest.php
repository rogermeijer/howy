<?php

namespace App\Http\Requests\Knowledge;

use App\Concerns\DocumentValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    use DocumentValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->documentAttributeRules(),
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
