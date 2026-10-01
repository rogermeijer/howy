<?php

namespace App\Http\Resources\Knowledge;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document as listed in the knowledge base: its metadata plus the state of
 * its current version.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->currentVersion;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'isCore' => $this->is_core,
            'language' => $this->language->value,
            'effectiveDate' => $this->effective_date?->toDateString(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'uploadedBy' => $this->uploadedBy?->name,
            'version' => $version === null ? null : [
                'id' => $version->id,
                'number' => $version->version_number,
                'filename' => $version->original_filename,
                'mimeType' => $version->mime_type,
                'sizeBytes' => $version->size_bytes,
                'pageCount' => $version->page_count,
                'status' => $version->status->value,
                'error' => $version->error,
                'createdAt' => $version->created_at?->toIso8601String(),
            ],
        ];
    }
}
