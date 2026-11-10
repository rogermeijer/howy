<?php

namespace App\Services\Knowledge\Extraction;

use App\Models\DocumentVersion;
use App\Services\Knowledge\Ai\Agents\ImageInterpreter;
use App\Services\Knowledge\Ai\AiGateway;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image as AiImage;

/**
 * Hands an image to the vision model, the way a scanned page is: only the
 * image itself leaves the application.
 */
class LlmImageReader implements ImageReader
{
    public function __construct(private readonly AiGateway $ai) {}

    public function read(DocumentVersion $version): ?ImageContent
    {
        $format = $version->format();

        if (! $format->isImage()) {
            return null;
        }

        $base = (string) tempnam(sys_get_temp_dir(), 'cc-image-');
        $file = $base.'.'.$format->extension();
        file_put_contents($file, Storage::disk($version->disk)->readStream($version->path));

        try {
            $result = $this->ai->structured(
                new ImageInterpreter,
                "The attached image was shared as \"{$version->original_filename}\".",
                'image_read',
                $version,
                [AiImage::fromPath($file, $format->mimeType())],
            );
        } finally {
            @unlink($file);
            @unlink($base);
        }

        return new ImageContent(
            kind: (string) ($result['kind'] ?? 'photo'),
            worthKeeping: (bool) ($result['worth_keeping'] ?? true),
            title: trim((string) ($result['title'] ?? '')),
            description: trim((string) ($result['description'] ?? '')),
            markdown: trim((string) ($result['markdown'] ?? '')),
        );
    }
}
