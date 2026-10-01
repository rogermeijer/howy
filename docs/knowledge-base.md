# Knowledge base

Uploaded documents (PDF, Word) become a searchable, source-traceable knowledge base per account: sections,
chunks with context lines and embeddings, summaries, atomic facts and a folder tree. The design and its
trade-offs are in [`plan-kennisbank.md`](plan-kennisbank.md) (Dutch); this page is how it works and how to run it.

## Pipeline

Upload (`DocumentUploader`) stores the file and queues `ProcessDocumentVersion`, which runs one chain on the
`knowledge` queue. Every step writes a `document_processing_steps` row (the inspector's _Processing_ tab).

| Step                | Job                                                  | Does                                                                                                                                          | Without AI key                   |
| ------------------- | ---------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------- |
| extract             | `ExtractDocumentText`                                | poppler (`pdftohtml -xml`) / pandoc (`--to=json`) → blocks, stored as `*.extracted.json`; scanned pages cut out with qpdf and read by the LLM | scanned pages stay unread        |
| structure           | `BuildDocumentStructure`                             | section tree, `SectionMatcher` against the previous version, `StructuralChunker`; reuses context + embedding of identical chunks              | same                             |
| contextualize       | `ContextualizeChunks`                                | one context line per chunk, groups of 16 with the whole document as cached prefix                                                             | skipped                          |
| embed               | `EmbedChunks`                                        | `text-embedding-3-large` @ 1024 dims; then swaps this version in (`is_current`) → **searchable**                                              | skipped, searchable by full text |
| enrich              | `EnrichDocumentVersion` (+ `CollectEnrichmentBatch`) | carries over unchanged sections; summary + facts per section via the Batch API (or sync)                                                      | skipped → ready                  |
| topics, embed_facts | `FinishEnrichment`                                   | document summary, folder tree / filing, fact embeddings → **ready**; queues `RefreshTopicSummaries`                                           | —                                |

A failing AI step never makes a document unfindable: context lines and embeddings give up after the last
attempt and the chain continues; once a version is searchable, a later failure leaves it searchable with the
error shown. _Process again_ reuses everything the version already had.

Statuses: `queued → extracting → structuring → contextualizing → embedding → searchable → enriching → ready`,
or `failed` (protected, oversized or empty files fail once, without retries).

## Data

All tables carry `account_id` and use `BelongsToAccount`. Polymorphic columns use the morph map in
`AppServiceProvider` (`document_version`, `document_section`, `knowledge_fact`, `knowledge_topic`, `email`, …);
chunks and facts have a `source` so mail-derived knowledge can share the same index later.

- `knowledge_chunks.search_vector` and `knowledge_facts.search_vector` are generated `tsvector` columns from a
  per-row `search_config` (`dutch` / `english`, from the document language).
- `embedding` columns are `vector(1024)` with partial HNSW indexes (`WHERE is_current`, `WHERE status <> 'expired'`).
  The dimension is `config('knowledge.embeddings.dimensions')`; changing it needs a migration plus
  `php artisan knowledge:reembed`. Changing only the model needs just the command.
- `knowledge_topics.path` is an `ltree` of ids (`12.45.88`): subtree queries are `path <@ '12.45'`. Depth is at
  most `knowledge.topics.max_depth` (3), enforced by a check constraint and by `TopicTree`.

## Search

`KnowledgeSearch` (`HybridKnowledgeSearch`) ranks current chunks twice — pgvector cosine distance (with
`hnsw.iterative_scan` and a minimum similarity of `knowledge.search.min_similarity`; a hit only the vector
search found must also clear `min_similarity_vector_only`, 0.45) and full text (OR of the
question's words, `ts_rank_cd`) — and fuses the ranks with reciprocal rank fusion (k = 60). Hits widen to their
whole section when it fits, else to the hit and its neighbours, until `maxTokens`. Facts are ranked the same
way and count against the budget first. Filters: document type, effective date, fact status, folder subtree.

**Tenancy**: the query is built only from `KnowledgeChunk` / `KnowledgeFact` Eloquent builders, so the account
scope is in every subquery; the fusion step selects from those subqueries, never from a table. Raw SQL
fragments only use literal table names (PHPStan enforces literal strings).

From the terminal: `php artisan knowledge:search {account} "question" --tokens=1500`.

## AI

Everything goes through `App\Services\Knowledge\Ai\AiGateway` (laravel/ai): it checks a provider is
configured, picks the model per step from `config('knowledge.llm.models')`, and records every call in
`ai_usage_records` (tokens, cached tokens, estimated cost per account and step). Agents live in
`app/Services/Knowledge/Ai/Agents`. The Batch API runs through `OpenAiBatchRunner` (plain HTTP; laravel/ai has
no batch support).

What leaves the application: document text (and, for scanned pages, those pages) — never account or user
names, ids or addresses. `OPENAI_STORE=false` is the default.

| Setting                                                  | Default                                            |
| -------------------------------------------------------- | -------------------------------------------------- |
| `OPENAI_API_KEY`                                         | — (without it: extraction + full-text search only) |
| `KNOWLEDGE_ENRICHMENT_MODE`                              | `batch` (`sync` locally)                           |
| `KNOWLEDGE_MODEL_*`                                      | `gpt-6-luna` for every LLM step                    |
| `KNOWLEDGE_PDFTOHTML`, `…_PDFINFO`, `…_QPDF`, `…_PANDOC` | the bare command name                              |

## Folders

`TopicOrganizer` proposes the first tree (5–8 domains, max three levels) once an account has processed
documents, then files sections into it; a proposed folder too similar to an existing one
(`topics.dedupe_similarity`, embedding cosine) is reused, and a folder past `topics.split_threshold` direct
links is split. AI-made folders are `review_status = new` until approved. Anything with `origin = manual`
(created, renamed, moved or linked by an admin) is never changed by AI. Overviews are rewritten bottom-up for
`summary_stale` folders by `RefreshTopicSummaries`.

## Running it

**Local (macOS):** `brew install postgresql@18 pgvector poppler pandoc qpdf redis`, create the `cc` and
`cc_testing` databases, set `QUEUE_CONNECTION=redis` and the `KNOWLEDGE_*` binary paths if your PHP processes
do not see `/opt/homebrew/bin`. `composer dev` starts Horizon, which works the `default` and `knowledge` queues.

**Server (Ubuntu, Forge-like):**

```bash
# PostgreSQL 18 + pgvector from the PGDG repository
sudo apt install postgresql-18 postgresql-18-pgvector poppler-utils pandoc qpdf redis-server
# once, as the postgres superuser (the app's database user usually is not one)
sudo -u postgres psql -d cc -c 'CREATE EXTENSION IF NOT EXISTS vector; CREATE EXTENSION IF NOT EXISTS ltree;'
```

- Run Horizon as a daemon (`php artisan horizon`) and the scheduler (`schedule:run` every minute), which runs
  `knowledge:poll-batches` every five minutes.
- `retry_after` on the queue connections is 960s, above the knowledge jobs' 900s timeout; keep it that way or a
  long job is picked up twice.
- Store originals on an EU S3-compatible bucket with `KNOWLEDGE_DISK=s3` (configure the `s3` disk).

## Measuring

- `php artisan knowledge:eval {account} --set=database/eval/kennisbank.json --label=…` — hit@k, MRR, recall,
  fact rate, tokens and latency per category; reports land in `storage/app/private/eval/`. The bundled set
  covers the example handbook in `tests/Fixtures/Knowledge`; extend it with real documents per tenant.
- `php artisan knowledge:usage --since=2026-10-01` — tokens and estimated cost per account, step and model.

## Tests

The suite never calls a provider: `phpunit.xml` forces an empty `OPENAI_API_KEY`, `Tests\Concerns\FakesKnowledgeAi`
fakes embeddings (deterministic bag-of-words vectors) and every agent, `Process::fake()` stands in for the CLI
tools (fixtures: `tests/Fixtures/Knowledge/*.pdf.xml`, `*.docx.json`), and the Batch API is faked over HTTP.
Isolation is covered per screen and for search.
