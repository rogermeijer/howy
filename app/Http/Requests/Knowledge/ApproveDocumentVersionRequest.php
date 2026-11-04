<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approving a version, with the conflicts the reviewer settles in favour of
 * what is known. The fact ids are resolved against this version's own facts
 * through the tenant scope, never trusted as they come.
 */
class ApproveDocumentVersionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'keep_existing' => ['sometimes', 'array'],
            'keep_existing.*' => ['integer'],
        ];
    }
}
