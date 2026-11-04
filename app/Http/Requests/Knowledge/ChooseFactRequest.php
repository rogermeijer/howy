<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A reviewer's choice about one fact of a version waiting for approval:
 * "new" or "keep" for a conflict, "omit" for a new fact, null to undo.
 */
class ChooseFactRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'choice' => ['present', 'nullable', 'string', 'in:new,keep,omit'],
        ];
    }
}
