# Knowledge base

Documents — uploaded, or shared as mail attachments — become a searchable, source-traceable knowledge base per
account: sections, chunks with context lines and embeddings, summaries, atomic facts and a folder tree. Mail adds
statements, and history too long to read as a mail is filed as a document too. Nothing is searched until an
administrator approves it, and nothing is read twice. The design and its trade-offs are in
[`plan-kennisbank.md`](plan-kennisbank.md) (Dutch); this page is how it works and how to run it.

**Formats** (`App\Enums\DocumentFormat`): PDF (poppler); docx, pptx, xlsx, odt, rtf, csv, tsv, html and
markdown (pandoc's readers, one JSON AST for all); plain text (no tool); png, jpeg, webp and gif (read by the
vision model, `ImageInterpreter`, step `image_read`). Old binary Office files (.doc, .xls, .ppt) are refused.

## Pipeline

Every file enters through `DocumentIntake` — an upload (`DocumentUploader`) and a mail attachment
(`MailAttachments`) alike — which stores it and queues `ProcessDocumentVersion`, one chain on the `knowledge`
queue. Every step writes a `document_processing_steps` row (the inspector's _Processing_ tab).

| Step                | Job                                                  | Does                                                                                                                                                                                                        | Without AI key                            |
| ------------------- | ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| extract             | `ExtractDocumentText`                                | poppler (`pdftohtml -xml`) / pandoc (`--to=json`) → blocks, stored as `*.extracted.json`; scanned pages cut out with qpdf and read by the LLM; an image read by the vision model; `text_sha256` of the text | scanned pages stay unread; an image fails |
| place               | `PlaceDocumentVersion`                               | for a version waiting for approval: same text known → mapped (mail) or hinted (upload); a mailed file sharing sections or its name with a live document becomes its next version; decoration turned down    | same                                      |
| structure           | `BuildDocumentStructure`                             | section tree, `SectionMatcher` against the previous version, `StructuralChunker`; reuses context + embedding of identical chunks                                                                            | same                                      |
| contextualize       | `ContextualizeChunks`                                | one context line per chunk, groups of 16 with the whole document as cached prefix                                                                                                                           | skipped                                   |
| embed               | `EmbedChunks`                                        | `text-embedding-3-large` @ 1024 dims; then swaps this version in (`is_current`) → **searchable**                                                                                                            | skipped, searchable by full text          |
| enrich              | `EnrichDocumentVersion` (+ `CollectEnrichmentBatch`) | carries over unchanged sections; summary + facts per section via the Batch API (or sync)                                                                                                                    | skipped → ready                           |
| topics, embed_facts | `FinishEnrichment`                                   | document summary, folder tree / filing, fact embeddings → **ready**; queues `RefreshTopicSummaries`                                                                                                         | —                                         |
| reconcile           | `FinishEnrichment`                                   | waiting for approval: each new fact against other documents and mail (`FactReconciler`) → **review**                                                                                                        | —                                         |

A failing AI step never makes a document unfindable: context lines and embeddings give up after the last
attempt and the chain continues; once a version is searchable, a later failure leaves it searchable with the
error shown. _Process again_ reuses everything the version already had.

Statuses: `queued → extracting → structuring → contextualizing → embedding → searchable → enriching → ready`
for a version that is already published (processed again), and `… → embedding → enriching → review` for a new
one; `failed` (protected, oversized or empty files fail once, without retries) and `rejected`.

## Ready for approval

Every new version — uploaded or mailed — is processed in full and then stops at `review`: its chunks are not
current and its facts stay `proposed`, so nothing of it is searched. `documents.current_version_id` is the
**published** version; a new version of a live document leaves the old one answering until it is approved.
The inspector shows a review panel (`DocumentInspectorController::review`): what it replaces, its sources,
where it was placed, sections unchanged / changed / added / removed, its new facts as new, already known or
contradicting (side by side, with the explanation), and the folders it would be filed in. The top bar and the
knowledge page count what waits (`knowledge.awaitingApproval`, admins only).

`DocumentReview::approve` publishes in one transaction: chunks swap (`DocumentVersion::makeCurrent`), the
previous version hands over (`SectionEnrichment::handOver`: unchanged facts move, the rest expire and are
superseded), facts become `core` (core document) or `supplementary`, a contradicted fact expires — unless the
reviewer keeps it, then the new fact goes. An older draft of the same document is turned down; a newer one is
processed again against what is now live. `reject` removes everything the version brought but keeps the row
as `rejected`, so the same file sent again is recognised and stays out; uploading it again on purpose is a new
decision. A document with only rejected versions is not listed.

## Read once

Nothing is processed or added twice. Each unit has a fingerprint, and what is known is context, never a source:

| Unit              | Fingerprint                                                                                    | When it was seen before                                                                        |
| ----------------- | ---------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| file              | `document_versions.sha256` (rejected ones included)                                            | an upload is refused; a mail is added as one more `document_sources` row, nothing is processed |
| file content      | `document_versions.text_sha256`                                                                | a re-exported copy by mail is mapped to the existing version; an upload gets a hint            |
| document          | section `content_hash` overlap (≥ `placement.min_section_overlap`) or the file name's stem     | a mailed file becomes the next version, so only what changed is processed and reviewed         |
| mail message      | `message_id_header` across the account's mailboxes                                             | the same mail in a second mailbox is not read again (`Skipped`)                                |
| paragraph / quote | `email_fingerprints` (normalised: letters and digits only, per paragraph and whole message)    | left out of the text the classifier gets; a mail with nothing new makes **no AI call**         |
| statement         | `content_hash`, then the nearest facts — published and **pending** — via `FactConflictChecker` | `duplicate` (not proposed again) or `conflict` (shown side by side)                            |

`MailNovelty` splits a mail into its new text (paragraphs read before replaced by `[…]`), known quotes
(context) and unseen quotes (a source). Fingerprints are written when a mail is read (`read`), when history is
taken from (`statements`) or filed (`document`), and for every reply Howy sends (`reply`), so its own answers
quoted back are not news. Forwarded-as-attachment mails (message/rfc822) come along as quotes with their
Message-ID. Mail read before fingerprints existed: `php artisan emails:fingerprint`.

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
`ai_usage_records` (tokens, cached tokens, estimated cost per account and step, plus the agent, latency,
outcome — failed calls too — and, for mail, the provider thread). What was sent and received goes, encrypted,
into `ai_call_payloads`; `knowledge:prune-ai-payloads` (daily) deletes those after
`AI_LOG_PAYLOAD_RETENTION_DAYS` (30), while the usage records stay for cost analysis. Super-admins read both
at `/admin/ai`, across accounts, through `App\Services\Admin\AiUsageLog` (the sanctioned exception in
CLAUDE.md rule 5). Prices in `knowledge.pricing` also match dated snapshots (`gpt-6-luna-2026-…`). Agents live in
`app/Services/Knowledge/Ai/Agents`. The Batch API runs through `OpenAiBatchRunner` (plain HTTP; laravel/ai has
no batch support).

What leaves the application: document text (and, for scanned pages, those pages), images shared as documents,
attachment file names and the senders' names and dates of quoted history (addresses masked) — never account or
user names, ids or addresses. `OPENAI_STORE=false` is the default.

| Setting                                                  | Default                                            |
| -------------------------------------------------------- | -------------------------------------------------- |
| `OPENAI_API_KEY`                                         | — (without it: extraction + full-text search only) |
| `KNOWLEDGE_ENRICHMENT_MODE`                              | `batch` (`sync` locally)                           |
| `KNOWLEDGE_MODEL_*`                                      | `gpt-6-luna` for every LLM step                    |
| `AI_LOG_PAYLOADS`                                        | `true` (keep what was sent, encrypted)             |
| `AI_LOG_PAYLOAD_RETENTION_DAYS`                          | `30`                                               |
| `KNOWLEDGE_PDFTOHTML`, `…_PDFINFO`, `…_QPDF`, `…_PANDOC` | the bare command name                              |
| `KNOWLEDGE_MAIL_ATTACHMENTS`                             | `true` (read attachments of mail to a mailbox)     |
| `KNOWLEDGE_MAIL_ATTACHMENT_MAX_KB`                       | `25600` (Gmail's own limit)                        |

## Mail to a mailbox

Every new mail to a connected mailbox is read and acted on, in one of two roles. `StoreGmailMessage` decides with
`EmailEligibility`: push or poll only (never an import), not from the mailbox itself, and not automated
(`Auto-Submitted`, `Precedence: bulk|list|junk|auto_reply`, `List-Id`). The role follows where the mailbox is:

- **addressed** — the mailbox is in `To`: cc: is asked, and answers the sender, copying in everyone else the mail
  was addressed or copied to (reply to all), so they see cc: has picked it up.
- **copied** — the mailbox is only in `Cc`: cc: listens. It never writes to the sender. A question to someone
  else gets a suggested answer, sent to the people in `To` only, when the answering model is at least
  `knowledge.mail.suggestion_min_confidence` (0.75) sure; otherwise the answer is kept on the mail as
  `unsure`. What people answer in the thread is filed as information, read with the message it replies to
  (`In-Reply-To`, else the quoted text) so that "yes, up to three days" becomes a claim of its own. A
  conflict is flagged, without a mail.

It creates an `email_interpretations` row with the role (`mode`) and queues `InterpretEmail` (the email id,
`knowledge` queue).

`EmailInterpreter` then:

1. **Classifies** with `EmailClassifier` on the light model (step `mail_classify`): `question`,
   `information` or `other`, a one-line summary, the language, and the standalone question or the atomic
   statements. Only the subject and the mail's new text are sent, with addresses masked.
2. **Question** → hybrid search; `QuestionAnswerer` (step `mail_answer`) answers only from the sources it
   gets, citing them, with a confidence. A partial answer is given as far as the sources go, with what they
   do not tell (`answer_gaps`) stated in the reply. A reply in the thread that fills that gap is read with it
   as context, and filed. No sources or no answer → `not_found`; addressed, the sender is told
   so. Copied: `suggested` (sent to the people asked) or `unsure` (kept, not sent).
3. **Information** → per statement: an identical fact (same `content_hash`) is a duplicate; otherwise the most
   similar facts go to `FactConflictChecker` (step `mail_conflicts`) in one call. New statements become
   `proposed` facts with `source_type = email`: embedded, but not searched until reviewed. The classifier
   `flag`s a statement that looks like general advice, an opinion, or no answer to the question.
   Contradicting statements are **held** (only in `email_interpretations.statements`); addressed, the sender
   gets a reply that sets each against the fact it contradicts and that fact's source.
4. **Other** → `no_action`.

**Attachments** are documents. Before the classifier runs, `MailAttachments` sets aside what cannot be read
without downloading it (unknown format, over the limit, an image under `attachments.min_image_bytes`, a
forwarded `.eml`), downloads the rest and hashes it. A file the knowledge base has is mapped to this mail as one
more source — the thread is linked to the existing document, nothing is processed again. The new ones are
listed in the prompt; the classifier decides per attachment (`knowledge` / `ignore`, with a reason), and
knowledge goes through `DocumentIntake` and waits for approval like an upload, its facts proposed like a
mail's. The decision is kept on the mail's attachment list; an admin can add a left-out attachment by hand
(`emails.attachments.store`). The mail waits in the inbox until its document is approved (`MailReviewState`).

**History.** Quoted or forwarded messages Howy has not read are a source: up to `mail.history_inline_chars`
they are given with the mail (`<forwarded>` / `<history>`, sender name and date only) and statements from them
are marked `from_history`; longer — or a mail longer than `mail.document_min_chars` — they are filed by
`ThreadDocumentWriter` as a `correspondence` document (one section per message, oldest first) that goes
through the pipeline and review like any other.

**Review.** Nothing from mail reaches the knowledge base by itself: every new or conflicting statement waits
(`needs_review`) until an administrator of the account approves or rejects it on the thread page
(`EmailStatementController`, `StatementReview`). Approving a new statement makes its fact `supplementary`;
approving a conflict adds it and expires the fact it contradicts (`superseded_by_id`); rejecting removes the
proposed fact. The top bar's inbox count is the number of mails waiting for review.

The row records `status` (`queued → processing → done | skipped | failed`), `intent`, `outcome`
(`answered`, `not_found`, `suggested`, `unsure`, `added`, `duplicate`, `conflict`, `no_action`), the question and answer with
citations, every statement with its verdict, and the reply. The inbox shows the outcome per thread; the thread
page shows the whole interpretation.

**Replies** are designed HTML (`resources/views/mail/cc/`, composed by `ReplyComposer`) with the same message as
plain text, which is also what the thread page shows. The Howy wordmark is embedded as an inline image
(`resources/images/mail/howy-logo@3x.png`, Plus Jakarta Sans 800 rendered at 3×, shown at 83×27), since mail clients load no web
fonts; re-render it (ImageMagick with Plus Jakarta Sans 800 and the lime dot) if the wordmark changes. They go out in the Gmail thread (`GmailClient::sendMessage`, `In-Reply-To`/`References`,
`Auto-Submitted: auto-replied`) from the mailbox, in the mail's language when we ship it. Each mailbox has a
send policy, set under _Settings → Mailboxes → Reply settings_:

| Policy      | Replies to                                    |
| ----------- | --------------------------------------------- |
| `off`       | nobody                                        |
| `domain`    | the mailbox's own domain, minus the blacklist |
| `whitelist` | only addresses and domains on the whitelist   |
| `always`    | everyone, minus the blacklist (default)       |

A blocked or failed reply is kept with its text (`reply_status` `blocked` / `failed`). A reply that went out
is never sent again, also not when the mail is interpreted again.

`php artisan mail:interpret {account} {email}` interprets a mail on demand (again, or one that was never
queued, such as an import); `--queue` queues it instead.

## Folders

`TopicOrganizer` proposes the first tree (5–8 domains, max three levels) once an account has processed
documents, then files sections into it; a proposed folder too similar to an existing one
(`topics.dedupe_similarity`, embedding cosine) is reused, and a folder past `topics.split_threshold` direct
links is split. AI-made folders are `review_status = new` until approved. Anything with `origin = manual`
(created, renamed, moved or linked by an admin) is never changed by AI. Overviews are rewritten bottom-up for
`summary_stale` folders by `RefreshTopicSummaries`.

## Running it

**Local (macOS):** `brew install postgresql@18 pgvector poppler pandoc qpdf redis`, create the `cc` and
`howy_testing` databases, set `QUEUE_CONNECTION=redis` and the `KNOWLEDGE_*` binary paths if your PHP processes
do not see `/opt/homebrew/bin`. `composer dev` starts Horizon, which works the `default` and `knowledge` queues.

**Server (Ubuntu, Forge-like):** the full runbook is `docs/deployment.md`; in short:

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

## Seed data

`php artisan knowledge:export-seed` snapshots every account's processed knowledge base — originals, extracted
text, sections, chunks with context lines and embeddings (packed float32), summaries, facts, folders, links,
processing steps and usage — into `database/seeders/data/knowledge/{account}.json.gz` plus `files/`.
`KnowledgeSeeder` (called from `DatabaseSeeder`) restores each snapshot into the account with the same name,
with new ids and every reference remapped, so `php artisan migrate:fresh --seed` gives back a ready knowledge
base in seconds without a single AI call. An account that already has documents is skipped.

Export again after changing documents; a snapshot is tied to the embedding model and dimensions it was made with.
The directory is gitignored: snapshots contain the documents themselves and stay on the machine that made them.

## Measuring

- `php artisan knowledge:eval {account} --set=database/eval/kennisbank.json --label=…` — hit@k, MRR, recall,
  fact rate, tokens and latency per category; reports land in `storage/app/private/eval/`. The bundled set
  covers the example handbook in `tests/Fixtures/Knowledge`; extend it with real documents per tenant.
- `php artisan knowledge:usage --since=2026-10-01` — tokens and estimated cost per account, step and model.
  `/admin/ai` shows the same with filters, per thread and message, and every call with its payload.

## Tests

The suite never calls a provider: `phpunit.xml` forces an empty `OPENAI_API_KEY`, `Tests\Concerns\FakesKnowledgeAi`
fakes embeddings (deterministic bag-of-words vectors) and every agent, `Process::fake()` stands in for the CLI
tools (fixtures: `tests/Fixtures/Knowledge/*.pdf.xml`, `*.docx.json`, `*.pptx.json`, `*.xlsx.json`, `*.csv.json`),
Gmail attachments and the Batch API are faked over HTTP, and `Tests\Concerns\ApprovesDocuments` approves what
a test needs searchable.
Isolation is covered per screen and for search.
