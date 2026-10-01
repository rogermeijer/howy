<?php

namespace App\Http\Requests\Knowledge;

use App\Concerns\DocumentValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentVersionRequest extends FormRequest
{
    use DocumentValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => $this->documentFileRules(),
        ];
    }
}
