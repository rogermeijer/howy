<?php

namespace App\Services\Knowledge\Extraction;

use App\Models\DocumentVersion;

/**
 * Used when no AI provider is configured: an image cannot be read.
 */
class NullImageReader implements ImageReader
{
    public function read(DocumentVersion $version): ?ImageContent
    {
        return null;
    }
}
