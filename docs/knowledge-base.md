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
A version waiting for approval opens on its **Review** tab (`DocumentInspectorController::review`,
`version-review.tsx`); the document's sections and its processing are tabs beside it. A decision bar on top
holds Approve and Reject. Each choice is saved the moment it is made (`knowledge_facts.review_choice`,
`knowledge.documents.versions.facts.choice`), so a review can be left and picked up later: per conflict "the new
fact" (`new`) or "the known fact" (`keep`), and for a new fact "leave out" (`omit`). Approving acts on the saved
choices and is refused while a conflict has none; left-out facts are never added. Below: conflicts side by side with the known fact and its source, new facts per section, what
expires, and folded rows for what needs no decision (already known, carried over, stays elsewhere). Picking a
fact shows where the document says it (`knowledge.documents.facts.source`, `FactPassage`): the section's
passage with the words marked — the `quote` the enricher copied from the section, kept only when it really
stands there; for facts from before quotes, the sentence sharing the most names and numbers with the fact.

**One approval place.** Everything that waits — document versions, uploaded or mailed, and the statements mail
would add or change — is listed on _Knowledge base → Ready for approval_ (`knowledge.approvals`,
`ApprovalController`, `ApprovalQueue`). A document is judged on its own page; a mail's statements are approved
or rejected right there. A mail itself never waits in the inbox: what it brought does, here. The top bar's
knowledge base count and the banner on the knowledge page count what waits (`knowledge.awaitingApproval`:
waiting versions plus mails with a pending statement, admins only).

`DocumentReview::approve` publishes in one transaction: chunks swap (`DocumentVersion::makeCurrent`), the
previous version hands over (`SectionEnrichment::handOver`: unchanged facts move, the rest expire and are
superseded), facts become `core` (core document) or `supplementary`, a contradicted fact expires — unless the
reviewer keeps it, then the new fact goes. An older draft of the same document is turned down; a newer one is
processed again against what is now live. `reject` removes everything the version brought but keeps the row
as `rejected`, so the same file sent again is recognised and stays out; uploading it again on purpose is a new
decision. A document with only rejected versions is not listed.

## Knowledge base language

Each account builds its knowledge base in one language, `accounts.knowledge_locale` (`KnowledgeLanguage`),
separate from its default interface language and, for a new account, the same until someone sets it.
Everything Howy writes itself is in it, whatever language the source is in: section and document summaries,
facts, folder names and folder summaries, statements from mail, and a conflict's explanation. A fact is
searched with that language's configuration (`search_config`). So the same claim in a Dutch and an English
document is one claim, and is recognised as known.

What stays in its own language: the document's text, its chunks and their context lines (search and citations
quote the source, with the document's own search configuration); answers and replies to mail, in the
sender's language; each user's interface language.

**Changing it** (_Settings → Knowledge base language_, admins, confirmed) queues `TranslateKnowledge`:
`KnowledgeTranslation` puts what is in another language into the new one with `KnowledgeTranslator` (step
`translate`, `translation.group_size` entries per request): facts per document or mail (told by their text, or
for a mail of a few statements by the language they were searched in), with their subject labels, new
`content_hash`, `search_config` and embedding; the statements of mails waiting for approval along with them;
section and document summaries per document; folder names, descriptions and summaries. Facts that then say the
same thing word for word become one fact with all their sources, and versions waiting for approval are checked
again. For knowledge built before there was one language: `php artisan knowledge:translate {account}
[--folders] [--queue]`. Safe to rerun.

A document's language is chosen at upload. A mailed file has nobody to choose it, and the mail's language is no
guide (a Dutch mail carrying an English handbook), so the placement step reads it from the text
(`TextLanguage`: the share of common words per shipped language; too little text or a mix is left alone).

## Sources of a fact

A fact is one claim, stated in as many places as say it (`knowledge_fact_sources`, `FactSources`): a section of
a document, or a mail. Its own source columns on `knowledge_facts` stay its primary source, the place it is cited
from; every source, that one included, is a row, recorded when the fact is made. Each row keeps the title it had,
so a removed document can still be named.

- **Approving a document**: a new fact found already known (`review_verdict = duplicate`) is not added again; the
  version becomes one more source of the known fact. Only a fact that counts already takes sources on; one still
  waiting elsewhere is not relied on, and the document keeps its own.
- **Mail**: a statement that is already known adds the mail as a source of the known fact right away. It changes
  nothing that is known, so it waits for nobody.
- **A new version**: what its unchanged sections state moves along, and so does what a changed section says word
  for word again (same `content_hash`, `SectionEnrichment::carried`): that fact keeps its identity in the new
  section, its repeat goes, and the fact check skips it. Every other source in the previous version goes; a fact with no other place left expires (its sources kept as history), one stated elsewhere stays and is
  cited from there (`FactSources::promote`). The review panel shows this before anyone decides, from the same
  plan approval executes (`FactSources::handOverPlan`): "Expires on approval", per section (removed or changed,
  with the new fact that replaces it: the same words, else the same subject), and "Stays, stated elsewhere too".
- **Removing a document** is a choice about its facts, made in the remove dialog (no default; the request is
  refused without one). It shows how many facts are stated only there (`knowledge.documents.removal`). A fact
  stated elsewhere too always stays, cited from there. One stated only there is kept ("keep": it keeps counting
  and names the removed document) or removed ("remove"). Proposed and expired facts go with the document either
  way (`FactSources::release`).
- **Pages**: a folder lists every fact stated in its sections; a section in the inspector lists what it states,
  with the other places as "Also in"; search shows the cited source and how many more there are.

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
| `KNOWLEDGE_MAIL_ANSWER_MIN_CONFIDENCE`                   | `0.75` (answer a question only this sure)          |

## Mail to a mailbox

Every mail to a connected mailbox is stored; a new one is read and acted on, in one of two roles.
`StoreGmailMessage` decides with `EmailEligibility`: push or poll only (never an import), not from the mailbox
itself, not automated (`Auto-Submitted`, `Precedence: bulk|list|junk|auto_reply`, `List-Id`), and only from a
sender the mailbox's rules let Howy do something with (`Mailbox::access()`, see "Sender rules" below). Mail from
anyone else gets a `skipped` row saying so, and nothing more. What Howy may do with the sender is kept on the
interpretation (`sender_access`), as it was when the mail arrived. The role follows where the mailbox is:

- **addressed** — the mailbox is in `To` (with or without others): Howy is asked. It answers a question when the
  answering model is at least `knowledge.mail.answer_min_confidence` (0.75) sure, to the sender, copying in
  everyone else on the mail the rules accept (reply to all). A less sure answer is kept on the mail as `unsure`,
  not sent. Information is filed; a conflict is explained to the sender. When the mailbox is the only address in
  `To`, the sender expects Howy to act, so the classifier is told so (the one piece of envelope it gets) and a
  request ("write a paragraph about …", summarise, explain) counts as a question, answered in the form asked for.
  Thanks and out-of-office mail stay `other`, unanswered.
- **copied** — the mailbox is in `Cc` (or blind-copied, or reached through a group): Howy listens. It answers
  nothing and writes to no one. What people say in the thread is filed as information, read with the message
  it replies to (`In-Reply-To`, else the quoted text) so that "yes, up to three days" becomes a claim of its
  own. A conflict is flagged, without a mail.

It creates an `email_interpretations` row with the role (`mode`) and queues `InterpretEmail` (the email id,
`knowledge` queue).

`EmailInterpreter` then:

1. **Classifies** with `EmailClassifier` on the light model (step `mail_classify`): `question`,
   `information` or `other`, a one-line summary, the language, and the standalone question or the atomic
   statements. Only the subject and the mail's new text are sent, with addresses masked.
2. **Question** → copied: `no_action`, nothing is searched. Addressed: hybrid search; `QuestionAnswerer` (step
   `mail_answer`) answers only from the sources it gets, citing them, with a confidence. A partial answer is
   given as far as the sources go, with what they do not tell (`answer_gaps`) stated in the reply. A reply in
   the thread that fills that gap is read with it as context, and filed. Sure enough → `answered`, sent; less
   sure → `unsure`, kept. No sources or no answer → `not_found`, and the sender is told so.
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
(`emails.attachments.store`). The document waits in the approvals; the mail does not wait for it. The
classifier never makes a statement that only announces or describes an attachment ("the new handbook is
attached"): a mail whose point is to send a file has no statements, its attachments carry the content.

**History.** Quoted or forwarded messages Howy has not read are a source: up to `mail.history_inline_chars`
they are given with the mail (`<forwarded>` / `<history>`, sender name and date only) and statements from them
are marked `from_history`; longer — or a mail longer than `mail.document_min_chars` — they are filed by
`ThreadDocumentWriter` as a `correspondence` document (one section per message, oldest first) that goes
through the pipeline and review like any other.

**Review.** Nothing from mail reaches the knowledge base by itself: every new or conflicting statement waits
(`needs_review` marks a mail with one) until an administrator of the account approves or rejects it in the
knowledge base's approvals (`EmailStatementController`, `StatementReview`); the thread page only shows that it
waits. Approving a new statement makes its fact `supplementary`; approving a conflict adds it and expires the
fact it contradicts (`superseded_by_id`); rejecting removes the proposed fact.

The row records `status` (`queued → processing → done | skipped | failed`), `intent`, `outcome`
(`answered`, `not_found`, `unsure`, `added`, `duplicate`, `conflict`, `no_action`; `suggested` only on mail read
before copied mail stopped answering), the question and answer with
citations, every statement with its verdict, and the reply. The inbox shows the outcome per thread; the thread
page shows the whole interpretation.

**Replies** are designed HTML (`resources/views/mail/cc/`, composed by `ReplyComposer`) with the same message as
plain text, which is also what the thread page shows. The Howy wordmark is embedded as an inline image
(`resources/images/mail/howy-logo@3x.png`, Plus Jakarta Sans 800 rendered at 3×, shown at 83×27), since mail clients load no web
fonts; re-render it (ImageMagick with Plus Jakarta Sans 800 and the lime dot) if the wordmark changes. They go out in the Gmail thread (`GmailClient::sendMessage`, `In-Reply-To`/`References`,
`Auto-Submitted: auto-replied`) from the mailbox, in the mail's language when we ship it, and only to senders
the mailbox's rules let Howy answer (see "Sender rules" below).

### Sender rules

Set on the mailbox's card under _Settings → Mailboxes_ (click the card): one access for colleagues, and a list of
exceptions; past three per access the card shows +N, which opens a side panel with all of them (search, filter,
change several at once). Per sender, a `SenderAccess` says what Howy may do with their mail:

| Access         | Learns | Answers | Meaning                                      |
| -------------- | ------ | ------- | -------------------------------------------- |
| `learn_answer` | ✓      | ✓       | everything below                             |
| `learn`        | ✓      | –       | files their mail, never writes to them       |
| `answer`       | –      | ✓       | answers their questions, files nothing       |
| `ignore`       | –      | –       | stores their mail as `skipped`, nothing more |

**Learning** is everything that adds to the knowledge base: proposing statements (`file()`), taking
attachments, filing long history as a correspondence document, and fingerprinting what was read. Not learned,
a mail leaves no fingerprints, so the same text forwarded later by a sender Howy learns from is still new.
**Answering** is everything that writes: answering a question, the not-found reply, a conflict reply, and
being copied in on a reply. `Mailbox::maySendTo()` is `answers()`, checked again when a reply is sent. There
is no switch for replies apart from that: a mailbox that should answer no one has no sender on an access that
answers. A sender Howy may only answer gets nothing done when the mailbox is only copied, so that mail is
`skipped` at once, without a model call. The interpretation's `note` says why a question went unanswered or
information was not learned.

Rules live in `mailbox_rules` (`MailboxRule`), each for one **colleague** (`colleague_id`) or one **domain**
(`domain`, without its `@`, lowercased), never both (a check constraint). `Mailbox::access()` takes the first of
these that matches (mirrored in `resources/js/lib/mailbox-rules.ts`):

1. the rule of the colleague with the sender's address;
2. the rule for the sender's domain (exactly; subdomains do not match). The mailbox's own domain is a domain rule
   like any other, shown on the card as "Colleagues" (default `learn_answer`).

Anyone else is ignored; to read someone else, list them.

A colleague's rule beats their domain's, so one address inside an organisation can be ignored while the rest is
read (`klant.nl` → `learn_answer`, `noreply@klant.nl` → `ignore`). Listing an address makes that person a
`Colleague` (one per address per account, see "Colleagues" in [`adoption-mails.md`](adoption-mails.md)) with a
rule of their own; removing the rule keeps the colleague, whose domain's rule then decides. At most 500 per save.
`MailboxRuleEditor` writes rules, and every change is aimed: the settings form sends the rules it sets and the
ones it removes (`removed_rules`), never "the whole list", so a rule added elsewhere since the page loaded (by
another admin, or on the colleagues page) survives a save. A rule that stays keeps its row.

A domain rule says whether the people it lets in become colleagues (`creates_colleagues`): always for the own
domain, off by default for any other (switch it on for a subsidiary). On a public domain (`config/mailboxes.php`:
gmail.com, outlook.com, …) the own domain would be everyone, so there is no rule for it and such a domain is never
a rule of its own: its addresses are listed one by one. A mailbox connected on one starts with only whoever
connected it, as a colleague with a rule of their own; with no rules it reads no one.

Older mail comes in only through the import dialog (its card's ⋯ menu), where you choose which emails; imported
mail is kept, never interpreted. A mailbox that never imported anything offers this once, on its card: Import…
opens the dialog, and the import ends the offer; No need sets `import_offer_answered_at`
(`MailboxController::dismissImportOffer`).

Replies go through `HowyMailer`, which logs every mail Howy sends (see "Sending and the mail log" in
[`adoption-mails.md`](adoption-mails.md)). A blocked or failed reply is kept with its text (`reply_status`
`blocked` / `failed`); one that waits for its mailbox to be reconnected is `waiting`, and goes once it is. A
reply that went out, or waits, is never sent again, also not when the mail is interpreted again.

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
