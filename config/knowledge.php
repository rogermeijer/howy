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
            'topic_summary' => env('KNOWLEDGE_MODEL_TOPIC_SUMMARY', 'gpt-6-luna'),
            // Mail to a mailbox: a light model sorts it, the others act on it.
            'mail_classify' => env('KNOWLEDGE_MODEL_MAIL_CLASSIFY', 'gpt-6-luna'),
            'mail_answer' => env('KNOWLEDGE_MODEL_MAIL_ANSWER', 'gpt-6-luna'),
            'mail_conflicts' => env('KNOWLEDGE_MODEL_MAIL_CONFLICTS', 'gpt-6-luna'),
        ],
    ],

    /*
    | Summaries and facts per section: "batch" sends them to the provider's
    | batch API (half price, results within 24 hours, collected by
    | knowledge:poll-batches); "sync" runs them right away, e.g. locally.
    */
    'enrichment' => [
        'mode' => env('KNOWLEDGE_ENRICHMENT_MODE', 'batch'),
    ],

    'search' => [
        'candidate_limit' => 40,
        'max_tokens' => 4000,
        // Reciprocal rank fusion constant.
        'rrf_k' => 60,
        // Vector hits below this cosine similarity are noise, not answers.
        'min_similarity' => 0.35,
        // A hit that only the vector search found must clear a higher bar:
        // with context lines embedded, any question in the documents' domain
        // scores ~0.38 against them, while real answers score ~0.47 and up.
        'min_similarity_vector_only' => 0.45,
        'query_embedding_cache_hours' => 24,
    ],

    /*
    | Mail addressed to a mailbox is answered or filed. See docs/knowledge-base.md.
    */
    'mail' => [
        // Tokens of knowledge given to the model that answers a question.
        'answer_max_tokens' => 3000,
        // Existing facts a new statement is compared with.
        'conflict_candidates' => 5,
        // When the mailbox is only copied, an answer is suggested to whoever
        // was asked only when the answering model is at least this sure.
        'suggestion_min_confidence' => (float) env('KNOWLEDGE_MAIL_SUGGESTION_MIN_CONFIDENCE', 0.75),
        // Characters of the message a reply answers, given as context.
        'context_max_chars' => 3000,
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
