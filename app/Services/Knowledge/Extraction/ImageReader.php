<?php

namespace App\Services\Knowledge\Extraction;

use App\Models\DocumentVersion;

/**
 * Reads an image version: what it is, what it shows, the text on it. Null
 * when it could not be read.
 */
interface ImageReader
{
    public function read(DocumentVersion $version): ?ImageContent;
}
