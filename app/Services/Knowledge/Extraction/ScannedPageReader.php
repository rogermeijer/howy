<?php

namespace App\Services\Knowledge\Extraction;

use App\Models\DocumentVersion;

/**
 * Reads pages that have no text layer (scans), returning their blocks by page
 * number. Pages it could not read are simply absent from the result.
 */
interface ScannedPageReader
{
    /**
     * @param  list<int>  $pages
     * @return array<int, list<Block>>
     */
    public function read(DocumentVersion $version, array $pages): array;
}
