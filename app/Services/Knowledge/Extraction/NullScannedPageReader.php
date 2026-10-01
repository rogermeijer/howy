<?php

namespace App\Services\Knowledge\Extraction;

use App\Models\DocumentVersion;

/**
 * Used when no AI provider is configured: scanned pages stay unread and are
 * listed as such in the processing step.
 */
class NullScannedPageReader implements ScannedPageReader
{
    public function read(DocumentVersion $version, array $pages): array
    {
        return [];
    }
}
