<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approving a version: per conflict what holds ("new" replaces the known
 * fact, "keep" keeps it), and the new facts the reviewer leaves out. Fact
 * ids are resolved against this version's own facts through the tenant
 * scope, never trusted as they come.
 */
class ApproveDocumentVersionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'conflicts' => ['sometimes', 'array'],
            'conflicts.*' => ['string', 'in:new,keep'],
            'omit' => ['sometimes', 'array'],
            'omit.*' => ['integer'],
        ];
    }
}
