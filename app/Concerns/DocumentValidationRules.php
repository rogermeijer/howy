<?php

namespace App\Concerns;

use App\Enums\DocumentType;
use App\Enums\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait DocumentValidationRules
{
    /**
     * An uploaded original: a PDF or a .docx, checked by content and extension.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function documentFileRules(): array
    {
        /** @var list<string> $mimeTypes */
        $mimeTypes = config('knowledge.upload.mime_types');

        return [
            'required',
            'file',
            'extensions:pdf,docx',
            'mimetypes:'.implode(',', $mimeTypes),
            'max:'.(int) config('knowledge.upload.max_kilobytes'),
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function documentAttributeRules(bool $required = false): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$required ? 'nullable' : 'sometimes', 'string', 'max:255'],
            'type' => [$presence, Rule::enum(DocumentType::class)],
            'is_core' => [$presence, 'boolean'],
            'language' => [$presence, Rule::enum(Locale::class)],
            'effective_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
