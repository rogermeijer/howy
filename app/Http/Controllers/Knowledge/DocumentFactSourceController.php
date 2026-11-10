<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\KnowledgeFact;
use App\Services\Knowledge\Facts\FactPassage;
use Illuminate\Http\JsonResponse;

/**
 * Where in this document a fact is stated: its section's passage with the
 * words marked. Fetched when a reviewer picks a fact, so a long review does
 * not carry every passage along. A fact not stated in this document 404s.
 */
class DocumentFactSourceController extends Controller
{
    public function __invoke(Document $document, KnowledgeFact $fact, FactPassage $passage): JsonResponse
    {
        $found = $passage->for($fact, $document);
        abort_if($found === null, 404);

        return response()->json($found);
    }
}
