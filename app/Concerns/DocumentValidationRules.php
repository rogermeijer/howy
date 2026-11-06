<?php

namespace App\Concerns;

use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Enums\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait DocumentValidationRules
{
    /**
     * An uploaded original in a format the knowledge base reads, checked by
     * content and extension.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function documentFileRules(): array
    {
        return [
            'required',
            'file',
            'extensions:'.implode(',', DocumentFormat::allExtensions()),
            'mimetypes:'.implode(',', DocumentFormat::allMimeTypes()),
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
