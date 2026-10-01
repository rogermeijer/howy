<?php

/*
 * The knowledge base: uploaded documents are extracted, chunked, embedded and
 * enriched into a searchable, source-traceable store. See docs/plan-kennisbank.md.
 */

return [

    /*
    | Where originals and their extracted JSON live. Private: files are only ever
    | served through a controller with a tenant-scoped binding.
    */
    'disk' => env('KNOWLEDGE_DISK', 'knowledge'),

    /*
    | Processing runs on its own queue so a large document never holds up mail.
    */
    'queue' => env('KNOWLEDGE_QUEUE', 'knowledge'),

    'upload' => [
        'max_kilobytes' => (int) env('KNOWLEDGE_MAX_UPLOAD_KB', 51200),
        'max_pages' => (int) env('KNOWLEDGE_MAX_PAGES', 500),
        'mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ],

    /*
    | CLI tools used for extraction. Configurable because a web or queue process
    | often runs with a narrower PATH than a login shell (e.g. /opt/homebrew/bin).
    */
    'binaries' => [
        'pdftohtml' => env('KNOWLEDGE_PDFTOHTML', 'pdftohtml'),
        'pdfinfo' => env('KNOWLEDGE_PDFINFO', 'pdfinfo'),
        'pdftoppm' => env('KNOWLEDGE_PDFTOPPM', 'pdftoppm'),
        'qpdf' => env('KNOWLEDGE_QPDF', 'qpdf'),
        'pandoc' => env('KNOWLEDGE_PANDOC', 'pandoc'),
    ],

    'extraction' => [
        // Fewer characters than this on a page with content means "probably scanned".
        'scanned_page_min_chars' => 50,
        // Pages sent to the LLM fallback per request.
        'fallback_pages_per_request' => 20,
        'timeout_seconds' => 300,
    ],

    'chunking' => [
        'min_tokens' => 150,
        'target_tokens' => 500,
        'max_tokens' => 800,
        'overlap_tokens' => 50,
        // Chunks per context-line request; the whole document is the cached prefix.
        'context_group_size' => 16,
        // Above this, the context prompt uses the summary and neighbouring sections.
        'full_document_max_tokens' => 150000,
    ],

    'embeddings' => [
        'provider' => env('KNOWLEDGE_EMBEDDING_PROVIDER', 'openai'),
        'model' => env('KNOWLEDGE_EMBEDDING_MODEL', 'text-embedding-3-large'),
        // Changing this needs a migration and `php artisan knowledge:reembed`.
        'dimensions' => 1024,
    ],

    /*
    | The model per step. Each step can move to another model or provider on its
    | own once the evaluation says so.
    */
    'llm' => [
        'provider' => env('KNOWLEDGE_LLM_PROVIDER', 'openai'),
        'models' => [
            'extraction_fallback' => env('KNOWLEDGE_MODEL_EXTRACTION', 'gpt-6-luna'),
            'context' => env('KNOWLEDGE_MODEL_CONTEXT', 'gpt-6-luna'),
            'summary' => env('KNOWLEDGE_MODEL_SUMMARY', 'gpt-6-luna'),
            'facts' => env('KNOWLEDGE_MODEL_FACTS', 'gpt-6-luna'),
            'topics' => env('KNOWLEDGE_MODEL_TOPICS', 'gpt-6-luna'),
        ],
    ],

    'search' => [
        'candidate_limit' => 40,
        'max_tokens' => 4000,
        // Reciprocal rank fusion constant.
        'rrf_k' => 60,
        // Vector hits below this cosine similarity are noise, not answers
        // (text-embedding-3-large: related text scores ~0.45+, unrelated < 0.25).
        'min_similarity' => 0.35,
        'query_embedding_cache_hours' => 24,
    ],

    'topics' => [
        'max_depth' => 3,
        'max_per_section' => 3,
        // A proposed topic this similar to an existing one is linked, not created.
        'dedupe_similarity' => 0.88,
        // A topic with more direct links than this gets a split proposal.
        'split_threshold' => 25,
    ],

    /*
    | USD per million tokens, for the usage estimates. Batch requests are billed
    | at half. Update when the provider's prices change.
    */
    'pricing' => [
        'gpt-6-luna' => ['input' => 0.10, 'cached_input' => 0.01, 'output' => 0.50],
        'gpt-5.4-mini' => ['input' => 0.75, 'cached_input' => 0.075, 'output' => 4.50],
        'text-embedding-3-large' => ['input' => 0.13, 'cached_input' => 0.13, 'output' => 0.0],
    ],

];
