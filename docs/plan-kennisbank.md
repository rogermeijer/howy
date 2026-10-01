# Plan: kennisbank-module

Implementatieplan voor de kennisbank van `cc:`: upload → verwerking → opslag → ophalen, met een mappenboom (onderwerpen) en UI. Vastgesteld op 2026-10-01.

## Beslissingen van Roger (vastgelegd)

1. **De hele app gaat naar PostgreSQL 18 met pgvector**, inclusief tests en CI.
2. **Embeddings: OpenAI `text-embedding-3-large`, ingekort tot 1024 dimensies.**
3. **Extractie: CLI-tools (pandoc, poppler), met een LLM als fallback** voor gescande pagina's en
   pagina's met complexe tabellen.
   **Alle AI-interacties lopen voorlopig via OpenAI** (Roger: één provider, één DPA). De standaard is
   **`gpt-6-luna`**, met `gpt-5.4-mini` als terugvaloptie. Via `laravel/ai` blijft de provider per stap
   wisselbaar, dus Claude Haiku 4.5 kan later per stap terug als de evaluatie daarom vraagt.
4. **Eén kennisbank met een gedeelde index.** Chunks en feiten krijgen een polymorfe bron: nu
   `DocumentVersion`, later `Email`.
5. **Onderwerpen vormen een mappenboom: AI stelt voor, de admin cureert.**
    - **Maximaal 3 niveaus**: domein › onderwerp › subonderwerp (`config('knowledge.topics.max_depth')`).
    - **Er is één boom, en elke map heeft een soort** (`TopicKind`: theme, project, party). Mails over
      projecten en relaties komen later in dezelfde boom terecht.
    - **De AI maakt een nieuwe map direct aan, met een badge „nieuw (AI)”.** De admin kan die goedkeuren,
      hernoemen, samenvoegen of verwijderen. Nieuwe hoofdmappen maakt de AI alleen bij de eerste opzet
      (bootstrap); daarna alleen submappen.
    - Een handmatige wijziging (`origin = manual`) wordt nooit door AI overschreven.
6. **UI wordt direct op de bestaande tokens en `cc-*`-componenten gebouwd**, met de mockup als basis. Per fase
   volgen screenshots voor feedback.
7. **Weaviate valt af.** Het gaat om ~5k chunks per tenant. pgvector houdt alles in één database met echte FK's,
   en de tenant-scope blijft hetzelfde mechanisme. Weaviate zou een tweede tenancy-model en synchronisatie
   toevoegen.

---

---

## 0. Samenvatting, aannames en open vragen

**Afwijking van de opdracht:** de opdracht noemt de kolom `tenant_id`, maar het plan gebruikt overal
**`account_id`**. Dat is de enige naam die de CI-guard herkent (CLAUDE.md, regel 1).

**Aannames** (in het document gemarkeerd als „Aanname”):

- A1. Er staat nog geen productiedata in MySQL. De lokale data kan opnieuw uit Gmail geïmporteerd worden.
- A2. Hosting: een Ubuntu-VPS in de EU (Forge-achtig), met Redis beschikbaar. Productie gebruikt Horizon.
- A3. Alleen account-admins uploaden (bestaande middleware `account.admin`). Alle leden mogen lezen.
- A4. Alleen `.pdf` en `.docx`, maximaal 50 MB en 500 pagina's. Een `.doc` wordt geweigerd met een duidelijke
  melding.
- A5. Een nieuwe versie uploaden gebeurt expliciet, via de actie „Nieuwe versie” op een document. Bij een gelijke
  bestandsnaam doet de app alleen een suggestie.
- A6. „Kerndocument” is een vinkje per document. Alleen kerndocumenten leveren feiten op.
- A7. De documenttaal is standaard de locale van het account (nl of en) en is aanpasbaar. Die taal bepaalt de
  full-text-configuratie (`dutch` of `english`).
- A8. Documentinhoud mag naar OpenAI, voor zowel de embeddings als de LLM-stappen. Daar moet een
  verwerkersovereenkomst (DPA) voor zijn. Een opt-out per account komt later.
- A9. `gpt-6-luna` (uitgebracht op 22-09-2026) is goed genoeg voor Nederlandse samenvattingen, contextregels en
  feitextractie. Het model is nieuw en nog niet op Nederlands getest. De evaluatie in fase 5 vergelijkt het met
  `gpt-5.4-mini` en Claude Haiku 4.5.

**Open vragen** (niet blokkerend voor fase 0–2):

- Q1. Een OpenAI-project met EU-dataresidentie?
- Q2. Retentie: bij verwijderen een harde delete, inclusief bestanden en afgeleide data?
- Q3. Testdocumenten en vragen voor de evaluatieset: levert Roger 5–10 echte (geanonimiseerde) handboeken?

## 1. Datamodel

Alle tabellen krijgen `account_id` (FK, `cascadeOnDelete`, niet in `#[Fillable]`) en `use BelongsToAccount`
direct op het model. Elke unieke index is samengesteld met `account_id`. Embeddings krijgen `#[Hidden]`.

| Tabel / model                                          | Kernkolommen                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `documents` / `Document`                               | title, type (`DocumentType`-enum: handbook, policy, manual, documentation, other), is_core, language, effective_date, current_version_id, status (afgeleid van de huidige versie), uploaded_by_user_id, timestamps, soft deletes: nee                                                                                                                                                                                                                                                            |
| `document_versions` / `DocumentVersion`                | document_id, version_number, original_filename, mime_type, size_bytes, disk, path, **sha256**, page_count, status (`ProcessingStatus`: queued, extracting, structuring, contextualizing, embedding, searchable, enriching, ready, failed), error, extracted_path (json), processed_at. Unique (account_id, sha256) en (account_id, document_id, version_number)                                                                                                                                  |
| `document_sections` / `DocumentSection`                | document_version_id, **parent_id**, level, ordinal, heading, heading_path (bijvoorbeeld „3 Verlof › 3.2 Ziekmelding”), page_from, page_to, content_hash, token_count, previous_section_id, change_type (`SectionChange`: unchanged, changed, added)                                                                                                                                                                                                                                              |
| `knowledge_chunks` / `KnowledgeChunk`                  | **source_type/source_id** (morph: document_version, later email), document_id (nullable), section_id (nullable), ordinal, kind (text, table, list), content, context (de contextregel), page_from, page_to, token_count, content_hash, **embedding `vector(1024)`**, embedding_model, search_config (`regconfig`), **search_vector `tsvector` GENERATED STORED** = `to_tsvector(search_config, coalesce(context,'') ‖ ' ' ‖ content)`, is_current                                                |
| `knowledge_summaries` / `KnowledgeSummary`             | summarizable morph (document_version, document_section), text, token_count, input_hash, model. Unique (account_id, summarizable_type, summarizable_id)                                                                                                                                                                                                                                                                                                                                           |
| `knowledge_facts` / `KnowledgeFact`                    | source morph, document_id, document_version_id, section_id, chunk_id, statement, subject (een kort onderwerp-label), status (`FactStatus`: core, supplementary, expired), valid_from, valid_until, superseded_by_id, confidence, page_from, page_to, content_hash, embedding `vector(1024)`, embedding_model, search_config, search_vector (generated)                                                                                                                                           |
| `document_processing_steps` / `DocumentProcessingStep` | document_version_id, step (`ProcessingStep`-enum), status, attempts, started_at, finished_at, error, provider_batch_id, meta (json)                                                                                                                                                                                                                                                                                                                                                              |
| `ai_usage_records` / `AiUsageRecord`                   | subject morph, step, provider, model, input_tokens, output_tokens, cache_read_tokens, cache_write_tokens, is_batch, estimated_cost_micros, created_at. Generiek, zodat mailverwerking hier later ook op logt                                                                                                                                                                                                                                                                                     |
| `knowledge_topics` / `KnowledgeTopic`                  | parent_id, **path `ltree`** (op basis van ids, bijvoorbeeld `12.45.88`, zodat hernoemen het pad niet verandert), depth (1–3, met een check-constraint), kind (`TopicKind`: theme, project, party), name, slug, description, summary (gegenereerd, rolt op uit de submappen), summary_stale, origin (`TopicOrigin`: ai, manual), review_status (`TopicReview`: new, approved), embedding `vector(1024)` (voor ontdubbeling), timestamps. Unique (account_id, parent_id, slug). GiST-index op path |
| `knowledge_topic_links` / `KnowledgeTopicLink`         | topic_id, linkable morph (document, document_section, knowledge_fact, later email), relevance, origin (ai, manual). Unique (account_id, topic_id, linkable_type, linkable_id)                                                                                                                                                                                                                                                                                                                    |

**Indexen**

- HNSW, als partiële index alleen op actuele rijen:
    ```sql
    CREATE INDEX knowledge_chunks_embedding_hnsw ON knowledge_chunks
      USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)
      WHERE is_current;
    CREATE INDEX knowledge_facts_embedding_hnsw ON knowledge_facts
      USING hnsw (embedding vector_cosine_ops) WHERE status <> 'expired';
    ```
- GIN op beide `search_vector`-kolommen.
- B-tree op (account_id, is_current), (account_id, status) en (account_id, document_id), en op elke FK.

**Vectordimensie:** één configwaarde, `config('knowledge.embeddings.dimensions')` (1024). De migratie leest die
waarde. Een wijziging vraagt een nieuwe migratie en een re-embed-command (`knowledge:reembed`).
`embedding_model` per rij maakt een mismatch zichtbaar.

**Morph map:** `Relation::enforceMorphMap` met `document_version`, `document_section` en `email`.

**Risico's en afwegingen:**

- Elke versie krijgt eigen chunk-rijen (een goedkope DB-kopie) in plaats van rijen te delen tussen versies.
  Dat kost iets meer opslag, maar houdt de herleiding chunk → versie → sectie → pagina eenvoudig en juist.
- HNSW met een tenant-filter vindt soms te weinig resultaten. Daarom `SET LOCAL hnsw.iterative_scan =
relaxed_order`. Bij ~5k chunks per tenant kiest de planner vaak gewoon een exacte scan via de account-index,
  wat ook prima is.

## 2. Upload en deduplicatie

- `DocumentController@store` en `DocumentVersionController@store`, met een `StoreDocumentRequest` die regels
  hergebruikt uit een nieuwe `app/Concerns/DocumentValidationRules.php`. Die regels: `file`,
  `mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document` en
  `max:` uit de config.
- De sha256 wordt al tijdens de upload berekend (streaming, `hash_file`). Bestaat die hash al in het account,
  dan volgt een 422 met de melding „Dit bestand is al geüpload als {titel} (versie {n})”. De unieke index vangt
  race conditions af.
- Opslag: disk `knowledge`, pad `accounts/{account_id}/documents/{document_id}/v{n}/{sha256}.{ext}`. Daarna
  `ProcessDocumentVersion::dispatch($versionId)`, en de response komt direct terug.
- **Sectiematching bij een nieuwe versie** (`SectionMatcher`):
    1. Gelijke `content_hash`: `unchanged`. De samenvatting, de feiten en de chunks (met embedding en context)
       worden hergebruikt.
    2. Gelijk genormaliseerd `heading_path`, andere hash: `changed`.
    3. Wat overblijft: de koppen worden vergeleken op Levenshtein-ratio ≥ 0,8, of de body op shingle-Jaccard
       ≥ 0,6. Dat telt als `changed` (verplaatst of hernoemd).
    4. Rest: `added`. Oude secties zonder match: hun feiten worden `expired` met `valid_until`.

    Chunks worden daarnaast **los van secties** hergebruikt op `content_hash` + `embedding_model` binnen de vorige
    versie. Dat is de belangrijkste tokenbesparing. De documentsamenvatting wordt alleen opnieuw gemaakt als er
    een sectie veranderd is, en dan goedkoop, op basis van de sectiesamenvattingen.

## 3. Tekstextractie (advies: CLI met een LLM-fallback)

Er komt één tussenformaat, `ExtractedDocument`: een lijst blokken {type: heading(level), paragraph, list,
table; text; page}. Dat wordt als JSON opgeslagen naast het origineel, zodat opnieuw chunken nooit opnieuw
extraheren betekent.

- **DOCX:** `pandoc -f docx -t json`. Koppen, lijsten en tabellen blijven betrouwbaar behouden. Word kent geen
  vaste pagina's, dus `page` blijft `null` en de sectie dient als locator.
- **PDF:** `pdftohtml -xml -i` (poppler) levert tekst per pagina, plus lettergrootte en positie, en de outline
  (bladwijzers). Koppen komen uit de outline, of anders uit een lettergrootte-heuristiek per document. Er wordt
  per pagina gecontroleerd of er nog iets moet gebeuren:
    - minder dan ~50 tekens op de pagina: waarschijnlijk een scan;
    - kolomuitlijning die op een tabel wijst.

    Pagina's die die vlag krijgen, worden met `qpdf --pages` gebundeld en als PDF-bestand naar **`gpt-6-luna`**
    gestuurd (bestandsinvoer). Bij twijfel gaan ze in plaats daarvan als gerenderde pagina-afbeeldingen via
    `pdftoppm`. De output is gestructureerd: markdown met koppen, `<table>`-gemarkeerde tabellen en
    paginanummers. In fase 2 moeten de limieten voor de bestandsinvoer (pagina's, MB) worden geverifieerd. De
    bundelgrootte komt in de config.

- Alles loopt via de Laravel `Process`-facade, met een timeout. In tests wordt dat `Process::fake()`.
- Afgewezen:
    - PHP-native (smalot, phpword): zwak op koppen en tabellen.
    - Docling: beste kwaliteit, maar een extra Python-service om te onderhouden.

    Als Docling later toch nodig blijkt, is het een tweede implementatie van `TextExtractor`.

- Risico's:
    - De kopheuristiek kan falen op slecht opgemaakte PDF's. De evaluatieset meet dit.
    - Versleutelde PDF's krijgen de status `failed` met de melding „beveiligd bestand”.

## 4. Chunking (`StructuralChunker`)

- Er wordt gechunkt langs de sectieboom. Tokens worden geteld met een tiktoken-implementatie (`cl100k`/`o200k`,
  passend bij OpenAI).
- Grenzen in de config: **minimaal 150, doel 400–600, maximaal 800 tokens.**
    - Kleine secties worden samengevoegd met hun volgende sibling, binnen dezelfde parent.
    - Lange secties worden op alinea- en zinsgrenzen gesplitst, met **~50 tokens overlap**, alleen binnen de
      sectie.
- Een tabel wordt een eigen chunk (`kind = table`). Is de tabel groter dan het maximum, dan wordt hij per rij
  gesplitst en wordt de kopregel herhaald.
- De tekst die geëmbed wordt, is `"{documenttitel} › {heading_path}\n{context}\n\n{content}"`.
- **Contextregels** (contextual retrieval): per sectiegroep van ~15–20 chunks gaat één LLM-call de deur uit.
    - Het volledige document staat **vooraan, byte-identiek** in elke call, zodat de automatische prompt
      caching van OpenAI het voorvoegsel hergebruikt (vanaf 1024 tokens, cached input −90%). Er wordt een vaste
      `prompt_cache_key` per documentversie meegegeven. De calls voor één document lopen sequentieel, zodat de
      cache warm blijft.
    - De output is gestructureerd: `[{chunk_id, context}]`, 1–2 zinnen, in de taal van het document.
    - Is een document groter dan ~150k tokens, dan gaan de documentsamenvatting en de omliggende secties mee in
      plaats van het volledige document.
    - Afweging: groeperen is ~10× goedkoper dan per chunk, met een iets lagere precisie. De evaluatie meet dit.
    - De vaste systeeminstructies staan vóór het document. Zo deelt elke call van elk document dat voorvoegsel.

## 5. AI-stappen, modellen en kosten

De prijzen zijn geverifieerd op de officiële OpenAI-prijspagina (oktober 2026), in $ per miljoen tokens:

| Model                                                 | Input | Cached input | Output | Batch |
| ----------------------------------------------------- | ----- | ------------ | ------ | ----- |
| `gpt-6-luna` (standaard)                              | 0,10  | 0,01         | 0,50   | −50%  |
| `gpt-5.4-mini` (terugvaloptie, meer redeneervermogen) | 0,75  | 0,075        | 4,50   | −50%  |
| ter vergelijking: `claude-haiku-4-5`                  | 1,00  | 0,10         | 5,00   | −50%  |
| `text-embedding-3-large`                              | 0,13  | –            | –      | −50%  |

Luna is ~10× goedkoper dan Haiku 4.5 en `gpt-5.4-mini` zit in dezelfde prijsklasse als Haiku. Volgens
benchmarks van derden presteert Luna op of boven GPT-5.4. Voor Nederlands is dat niet aangetoond, dus de
evaluatie beslist.

| Stap                                                                                             | Model (config per stap)                                        | Modus                           | Schatting per document van 30 p. (~15k tokens, ~40 chunks, ~25 secties) |
| ------------------------------------------------------------------------------------------------ | -------------------------------------------------------------- | ------------------------------- | ----------------------------------------------------------------------- |
| OCR- of tabel-fallback (alleen gevlagde pagina's)                                                | gpt-6-luna                                                     | realtime                        | ~2k in / 0,7k uit per pagina, ≈ $0,0006 per pagina                      |
| Contextregels                                                                                    | gpt-6-luna                                                     | realtime + automatische caching | ≈ $0,004                                                                |
| Embeddings van chunks                                                                            | text-embedding-3-large, 1024 dim.                              | realtime                        | ~22k tokens ≈ $0,003                                                    |
| Sectiesamenvattingen                                                                             | gpt-6-luna                                                     | **OpenAI Batch API**            | ≈ $0,003                                                                |
| Documentsamenvatting                                                                             | gpt-6-luna                                                     | Batch                           | ≈ $0,0005                                                               |
| Feitextractie (alleen kerndocumenten)                                                            | gpt-6-luna, of gpt-5.4-mini als de evaluatie dat rechtvaardigt | Batch                           | ≈ $0,004 (mini: ≈ $0,03)                                                |
| Embeddings van feiten                                                                            | text-embedding-3-large                                         | realtime                        | verwaarloosbaar                                                         |
| Onderwerpen toekennen (per sectie, met de bestaande onderwerpenlijst van de tenant in de prompt) | gpt-6-luna                                                     | Batch                           | ≈ $0,002                                                                |
| Onderwerpsamenvatting (alleen bij `summary_stale`)                                               | gpt-6-luna                                                     | Batch                           | ≈ $0,001 per onderwerp                                                  |

**Mappenboom opzetten** (`BootstrapTopicTree`, eenmalig per account):

- Dit gebeurt zodra de eerste documenten verrijkt zijn, of handmatig via „Structuur voorstellen”.
- Het model krijgt alle documentsamenvattingen en de sectiekoppen. Het stelt 5–8 domeinen voor, met daaronder
  onderwerpen (maximaal 3 niveaus).
- Alles wordt aangemaakt met `review_status = new`.

**Onderwerpen toekennen** (`AssignTopics`, onderdeel van de verrijkingsbatch):

- Elke sectie krijgt 1–3 **bladmappen** (de meest specifieke). Het model krijgt de sectiesamenvatting en de
  boom als ingesprongen lijst, en kiest een bestaand pad. Alleen als niets past, mag het een nieuwe submap
  voorstellen onder een bestaande map, binnen de maximale diepte.
- Groeit een map voorbij ~25 directe koppelingen, dan wordt een splitsing in submappen voorgesteld
  (`SuggestTopicSplit`). Die verschijnt als „nieuw (AI)”.
- Een nieuw voorgesteld onderwerp wordt eerst geëmbed. Lijkt het voor ≥ 0,88 (cosine) op een bestaand
  onderwerp, dan wordt het aan dat onderwerp gekoppeld in plaats van aangemaakt.
- Feiten erven de onderwerpen van hun sectie. Een document hoort bij de unie van de onderwerpen van zijn
  secties.
- Bij een gewijzigde koppeling krijgen de map **en zijn voorouders** `summary_stale = true`. De volgende batch
  maakt de samenvattingen bottom-up opnieuw:
    - een blad: uit de gekoppelde feiten en sectiesamenvattingen, met bronverwijzingen;
    - een bovenliggende map: uit de samenvattingen van de submappen.

    Zo krijgen brede vragen het overzicht en specifieke vragen de passage.

- Zoeken kan beperkt worden tot een deelboom: `SearchQuery::$topicId` filtert via een join op de koppelingen
  met `path <@ :topicPath`.
- Handmatige koppelingen en onderwerpen (`origin = manual`) blijven altijd staan. AI-koppelingen van een
  vervallen sectie verdwijnen mee.

Dat komt neer op **≈ $0,015 per document, minder dan $1 per tenant met 50 documenten**, eenmalig. Met
Haiku zou dat ≈ $0,10–0,15 per document zijn. Een nieuwe versie kost alleen het deel dat veranderd is. Het
tokenzuinige ontwerp (hergebruik op content-hash) blijft belangrijk, omdat het de kosten ook laag houdt als er
later naar een duurder model wordt overgestapt.

**Abstractie en pakketten**

- **`laravel/ai`** (de first-party AI SDK van Laravel 13) voor realtime prompts en embeddings. Daarmee is de
  provider via config te wisselen, en zijn er fakes beschikbaar (`Ai::fake()`, `Ai::fakeEmbeddings()`).
    - Het pakket wordt **niet** gebruikt voor de opslag van conversaties of agents. Die tabellen hebben geen
      `account_id`.
- **`openai-php/client`** alleen in `OpenAiBatchRunner`, voor de Batch API (JSONL uploaden, `/v1/batches`,
  het resultaatbestand ophalen), omdat `laravel/ai` geen batches lijkt te ondersteunen. Dit wordt in fase 4
  geverifieerd. Ondersteunt het pakket het wel, dan vervalt de client. Er komt nu geen Anthropic-pakket; dat
  kan later achter hetzelfde `BatchRunner`-contract.
- Gestructureerde output (JSON-schema) voor contextregels en feiten, via `HasStructuredOutput` van
  `laravel/ai`.
- Eigen dunne contracten in `app/Services/Knowledge/Ai/`:
    - `Embedder::embed(list<string>): list<list<float>>`
    - `KnowledgeLlm` (contextregels, samenvatten, feiten, PDF-fallback)
    - `BatchRunner`

    Elke implementatie loopt via **`AiGateway`**, de centrale plek voor de privacy-check, usage-logging en het
    model per stap uit `config('knowledge.models.*')`.

## 6. Ophalen: `KnowledgeSearch`

```php
interface KnowledgeSearch { public function search(SearchQuery $query): SearchResult; }
final readonly class SearchQuery {
    public function __construct(
        public string $text,
        public array $documentTypes = [],          // list<DocumentType>
        public ?CarbonImmutable $effectiveFrom = null,
        public ?CarbonImmutable $effectiveUntil = null,
        public array $factStatuses = [FactStatus::Core, FactStatus::Supplementary],
        public bool $includeFacts = true,
        public int $candidateLimit = 40,
        public int $maxTokens = 4000,              // harde limiet voor de teruggegeven context
    ) {}
}
// SearchResult: list<Passage> passages, list<FactHit> facts, int tokenCount
// Passage: documentId, versionId, versionNumber, title, headingPath, pageFrom, pageTo, text, score, chunkIds
```

- De tenant komt **altijd** uit `Tenancy::id()`, nooit uit de input.
- **Raw SQL is op tenant-tabellen verboden (regel 9).** Daarom wordt de query gebouwd uit **twee gescopete
  Eloquent-subqueries** (`KnowledgeChunk::query()->selectRaw(...)`), die met `joinSub`/`fromSub` worden
  gecombineerd. Zo zit de global scope in elke subquery. Het document toont de SQL die eruit komt:

```sql
WITH vec AS (
  SELECT id, row_number() OVER (ORDER BY embedding <=> :qvec) AS rnk
  FROM knowledge_chunks
  WHERE account_id = :account AND is_current          -- door AccountScope
  ORDER BY embedding <=> :qvec LIMIT 40
), fts AS (
  SELECT id, row_number() OVER (ORDER BY ts_rank_cd(search_vector, q) DESC) AS rnk
  FROM knowledge_chunks, websearch_to_tsquery('dutch', :qtext) q
  WHERE account_id = :account AND is_current AND search_vector @@ q
  ORDER BY ts_rank_cd(search_vector, q) DESC LIMIT 40
)
SELECT c.id, c.section_id, c.token_count,
       coalesce(1.0 / (60 + vec.rnk), 0) + coalesce(1.0 / (60 + fts.rnk), 0) AS rrf
FROM vec FULL OUTER JOIN fts USING (id)
JOIN knowledge_chunks c ON c.id = coalesce(vec.id, fts.id) AND c.account_id = :account
JOIN documents d ON d.id = c.document_id AND d.account_id = :account
WHERE (:types IS NULL OR d.type = ANY(:types))
ORDER BY rrf DESC LIMIT 20;
```

- **Small-to-big:** treffers worden per sectie gegroepeerd. Past de hele sectie binnen het budget, dan gaat de
  volledige sectietekst terug. Anders de chunk plus de buurchunks (ordinal ±1). Het budget wordt gretig gevuld
  op RRF-score tot `maxTokens`.
- Feiten worden apart gezocht, met dezelfde RRF-opzet op `knowledge_facts` en een filter op status en datum.
- Query-embeddings worden 24 uur gecachet op een hash van de tekst.
- Reranker: een later haakpunt, `Reranker`-contract (Voyage of Cohere via `laravel/ai`). In fase 3 nog niet
  gebouwd.

## 6b. Kennisbank-UI (Finder-model: mappen = onderwerpen, bestanden = bronnen)

Alle schermen zitten onder de bestaande top-bar en gebruiken de `cc-*`-componenten (`cc-panel`,
`cc-row-file`, `PageHeader`, `Tag`, `Confidence`). Velden zijn solide en duidelijk begrensd. Alle tekst loopt
via `t()`, met de Nederlandse vertaling in `lang/nl.json`. De demodata in `knowledge.tsx` verdwijnt.

| Scherm                                           | Route                                                                          | Inhoud                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| ------------------------------------------------ | ------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Kennisbank-home**                              | `knowledge`                                                                    | Een groot, solide zoekveld. Een grid met onderwerp-mappen (bovenste niveau, met aantal bronnen en laatste wijziging). Een lijst „Recent bijgewerkt” met bestanden (documenten, later mail-items), met de kolommen naam, bron, type en bijgewerkt (de bestaande mockup-grid). De lijst- en gridweergave van de mockup blijven.                                                                                                                                                                                                                                                                                                                                     |
| **Onderwerp (map)**                              | `knowledge.topics.show`                                                        | Een breadcrumb met het volledige pad. Bovenaan staan de **submappen** als mappen-grid, met badges voor de soort en voor „nieuw (AI)”. Er is een schakelaar „Inclusief submappen” (standaard aan) voor feiten en bronnen. Er is een zoekveld dat binnen deze map zoekt. Een overzicht met de onderwerpsamenvatting en inline bronverwijzingen. Feiten, gegroepeerd in **Kern / Aanvulling / Vervallen** (dicht), met „geldig vanaf” en per feit een **bronchip** (`Handboek · v2 · §3.2 · p. 14`). Die chip leidt naar de documentinspector op die sectie; later gaat een mailchip naar `emails.show`. Daaronder submappen, bronbestanden en verwante onderwerpen. |
| **Documenten**                                   | `knowledge.documents.index`                                                    | Uploaden (solide dropzone en knop), de documentlijst met type, kern-vinkje, versie en een **statusbalk per document** (stappen queued → ready, live via `usePoll`), en de knoppen „Nieuwe versie” en „Opnieuw verwerken”.                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| **Document-inspector** („hoe is dit opgeslagen”) | `knowledge.documents.show`                                                     | Links de **sectieboom** met wijzigingsbadges (ongewijzigd, gewijzigd, nieuw). Rechts, voor de gekozen sectie: de samenvatting, de **chunks** (tekst, contextregel, pagina's, tokens, embedding ja/nee), de geëxtraheerde feiten en de onderwerpen. Een kop met versiekiezer en „Origineel openen”, waarmee de PDF inline opent op `#page=n`. Een tab **Verwerking** met een tijdlijn van de stappen, tokens en geschatte kosten per stap.                                                                                                                                                                                                                         |
| **Zoeken**                                       | `knowledge.search`                                                             | Resultaten van `KnowledgeSearch`: passages met bronchips, de bijbehorende feiten en de onderwerpen. Admins kunnen een debug-weergave aanzetten met de RRF-, vector- en FTS-rangen en de tokentelling.                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| **Onderwerp cureren** (alleen admins)            | `knowledge.topics.store` / `update` / `approve` / `merge` / `move` / `destroy` | Een map aanmaken, hernoemen, goedkeuren (ook in bulk: „Alle nieuwe mappen bekijken”), samenvoegen (koppelingen en submappen verhuizen), verplaatsen via „Verplaats naar…” (met een validatie op de maximale diepte, inclusief de diepte van de deelboom), verwijderen (de inhoud gaat naar de bovenliggende map), en een koppeling handmatig toevoegen of verwijderen. Een verplaatsing herschrijft het `ltree`-pad van de hele deelboom in één transactie.                                                                                                                                                                                                       |

- Data gaat via Inertia-props met expliciete resources. Er worden geen embeddings meegestuurd (`#[Hidden]`).
- Elk scherm heeft een isolatietest: een onderwerp, document of sectie van een ander account geeft 404.
- Componenten komen in `resources/js/components/knowledge/*` (`source-chip`, `status-pipeline`,
  `section-tree`, `chunk-card`, `fact-list`, `topic-folder`). De pagina's staan in
  `resources/js/pages/knowledge/*`.

## 7. Infrastructuur

- **Lokaal (macOS):** `brew install postgresql@18 pgvector`, of Postgres.app (pgvector zit erin), plus
  `brew install poppler pandoc qpdf`.
- **VPS (Ubuntu):** de PGDG-apt-repo, dan `postgresql-18 postgresql-18-pgvector poppler-utils pandoc qpdf`.
  `CREATE EXTENSION vector` moet één keer als superuser draaien, omdat Forge-databasegebruikers geen superuser
  zijn. Hetzelfde geldt voor `ltree`, dat standaard in Postgres contrib zit. De migratie draait
  `CREATE EXTENSION IF NOT EXISTS vector` en `... ltree` (`Schema::ensureVectorExtensionExists()` als
  Laravel 13 die heeft; dat wordt geverifieerd).
- **Queue:** Redis + **Horizon**, met een aparte supervisor voor de queue `knowledge`:
    - timeout 900 seconden, `tries` 3, `backoff` [60, 300, 900];
    - `retry_after` op de Redis-connectie moet groter zijn dan de timeout (1000);
    - `WithoutOverlapping` per document.

    Lokaal mag `database` blijven.

- **Pipeline:** `ProcessDocumentVersion` start een `Bus::chain` met:
    1. `ExtractDocumentText`
    2. `BuildDocumentStructure` (secties, matching, chunks)
    3. `ContextualizeChunks`
    4. `EmbedChunks`, waarna de status **searchable** wordt
    5. `SubmitEnrichmentBatch`

    Het command `knowledge:poll-batches` (scheduler, elke 5 minuten) verwerkt de resultaten en start dan
    `EmbedFacts`, waarna de status **ready** wordt.

    Alle jobs zijn `TenantAware`, gebruiken `InteractsWithTenancy` en `rememberTenant()`, en geven ids door
    (patroon `SyncGmailMailbox`). Elke stap schrijft een `DocumentProcessingStep`. Een mislukte stap is opnieuw
    te starten via de knop „Opnieuw verwerken”.

- **Opslag:** disk `knowledge`, standaard lokaal-privé. Via `.env` is een S3-compatibele EU-bucket mogelijk.
  Downloaden gaat via een controller met scoped binding, nooit via een publieke URL.
- **Privacy:** alleen OpenAI ontvangt data: de volledige documenttekst, de gevlagde PDF-pagina's en de tekst
  van chunks en feiten. Er gaan geen accountnamen, gebruikers of ids mee. Alles loopt via `AiGateway`. Daar komt
  later de opt-out per account. OpenAI traint niet op API-data, en er moet een DPA zijn. Een EU-dataresidentie-
  project (open vraag Q1) en `store: false` worden aanbevolen.

## 8. Kwaliteit en meten

- **Postgres in de tests:**
    - `phpunit.xml` wordt pgsql met een `cc_testing`-database.
    - CI krijgt de service `pgvector/pgvector:pg18`.
    - De guard-tests (`TenantSchemaGuardTest`, `WithoutTenancyGuardTest`) moeten groen blijven.
- **Unit tests:**
    - `StructuralChunker` (grenzen, tabellen, overlap)
    - `SectionMatcher`
    - de kopheuristiek (met fixture-XML in `tests/Fixtures/Knowledge/`)
- **Feature tests:**
    - de upload-flow (validatie, dedup met een 422, opslag, de job in de queue via `Queue::fake()`)
    - een nieuwe versie
    - statusweergave en isolatie: `DocumentIsolationTest` volgens het `MailboxIsolationTest`-patroon, plus een
      `KnowledgeSearchIsolationTest` die bewijst dat de chunks van een ander account nooit terugkomen
- **Mocks:** de pipeline wordt getest met `Process::fake()` en `Ai::fake()`/`Ai::fakeEmbeddings()`
  (deterministische vectoren). Batches lopen via een gefakete `BatchRunner`, of via een `ClientFake` van
  `openai-php`.
- **Modelvergelijking in de evaluatie:** `gpt-6-luna` tegenover `gpt-5.4-mini` (en eventueel Haiku 4.5) voor
  contextregels en feiten, met dezelfde vragenset. Vergeleken worden kwaliteit en kosten per document.
- **Evaluatie:** het command `knowledge:eval {account} {--set=database/eval/kennisbank.json}`.
    - De set bevat ~50 vragen, met per vraag het verwachte document, de verwachte sectie en pagina, en optioneel
      een verwacht feit.
    - Categorieën: feitopzoeking, tabel, meerdere secties, parafrase en synoniemen, Nederlandse samenstellingen,
      en „niet in de kennisbank”.
    - Metrieken: hit@5, MRR, recall van de bron, gemiddeld aantal teruggegeven tokens en latency.
    - De uitkomst gaat naar JSON in `storage/app/eval/`, om runs te kunnen vergelijken (bijvoorbeeld chunkgrootte,
      contextregels aan of uit).
- **Usage:** elke AI-call schrijft een `AiUsageRecord`. `knowledge:usage {--account} {--since}` aggregeert per
  tenant en per stap. De prijstabel staat in `config/knowledge.php`.

## 9. Fasering

| Fase                            | Oplevering                                                                                                                                                                                                                                                                                                                                                                                                                                         | Belangrijkste bestanden                                                                                                                                                                                                                                                               | Hoe getest                                                                               |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| **0. Postgres 18**              | De hele app op pgsql 18 met pgvector                                                                                                                                                                                                                                                                                                                                                                                                               | `config/database.php`, `.env.example`, `phpunit.xml`, `.github/workflows/tests.yml`, controle op MySQL-specifieke code, `docs/multi-tenancy.md`                                                                                                                                       | `composer ci:check` groen op Postgres, ook in CI                                         |
| **1. Schema en upload**         | Migraties (pgvector-extensie, alle tabellen en indexen), modellen, enums, factories, upload, nieuwe versie, dedup, de UI **Documenten** (upload, statuspipeline met `usePoll`) en de kennisbank-home met echte documenten (mappen worden verborgen tot er onderwerpen zijn), vertalingen in `lang/nl.json`                                                                                                                                         | `database/migrations/*_create_documents_*`, `app/Models/{Document,DocumentVersion,...}.php`, `app/Enums/{DocumentType,ProcessingStatus,FactStatus,...}.php`, `app/Http/Controllers/Knowledge/*`, `app/Concerns/DocumentValidationRules.php`, `config/knowledge.php`, `routes/web.php` | Feature tests voor upload, dedup en isolatie; guard-tests                                |
| **2. Extractie en chunking**    | `TextExtractor` (pandoc, poppler, LLM-fallback), `ExtractedDocument`, secties, `SectionMatcher`, `StructuralChunker`, de jobs Extract en Build, de UI **Document-inspector** (sectieboom, chunks, pagina's, versies, het origineel openen)                                                                                                                                                                                                         | `app/Services/Knowledge/Extraction/*`, `app/Services/Knowledge/Chunking/*`, `app/Jobs/Knowledge/*`                                                                                                                                                                                    | Unit tests met fixtures, `Process::fake()`, een pipeline-test tot de status „structured” |
| **3. Embeddings en zoeken**     | `laravel/ai` en de config, `AiGateway`, `Embedder`, `ContextualizeChunks`, `EmbedChunks`, `KnowledgeSearch` (RRF, small-to-big, tokenlimiet), `AiUsageRecord`, het command `knowledge:search`, de UI **Zoeken** (inclusief de debug-weergave) en het zoekveld op de home, contextregels en embeddingstatus in de inspector, de tab **Verwerking** met tokens en kosten                                                                             | `app/Services/Knowledge/Ai/*`, `app/Services/Knowledge/Search/*`                                                                                                                                                                                                                      | `Ai::fakeEmbeddings`, isolatietest op zoeken, een SQL-snapshot van de query              |
| **4. Samenvattingen en feiten** | `OpenAiBatchRunner`, `SubmitEnrichmentBatch`, `knowledge:poll-batches`, levenscyclus van feiten over versies heen, `EmbedFacts`, feitzoeken, **de mappenboom** (`ltree`-extensie, `knowledge_topics` en `knowledge_topic_links`, `BootstrapTopicTree`, `AssignTopics`, `SuggestTopicSplit`, ontdubbeling, bottom-up samenvattingen, zoeken in een deelboom), de UI **Onderwerp-mappen**, **Onderwerppagina** (feiten met bronchips) en **Cureren** | `app/Services/Knowledge/Enrichment/*`, `routes/console.php`                                                                                                                                                                                                                           | Gefakete `BatchRunner`; feiten worden `expired` na een gewijzigde sectie                 |
| **5. Evaluatie en productie**   | De eval-set en `knowledge:eval`, `knowledge:usage`, Horizon-config, VPS-runbook, `docs/knowledge-base.md`, een update van CLAUDE.md (morph map, AI-gateway)                                                                                                                                                                                                                                                                                        | `config/horizon.php`, `database/eval/*`                                                                                                                                                                                                                                               | Een eval-run met de baseline vastgelegd                                                  |

---

---

## Stand van de uitvoering (1 oktober 2026)

Fase 0 tot en met 5 zijn gebouwd op `feature/kennisbank`, met één commit per fase. Hoe het werkt en hoe je
het draait, staat in [`knowledge-base.md`](knowledge-base.md).

**Afwijkingen van het plan, met reden:**

- **Batch-API via Laravels Http-client in plaats van `openai-php/client`.** `laravel/ai` heeft geen
  batch-ondersteuning. De Batch-API bestaat uit vier eenvoudige REST-calls, en de bestaande `GmailClient`
  volgt hetzelfde patroon. Er is dus geen extra pakket nodig, en `Http::fake()` dekt de tests.
- **Kleine secties worden niet samengevoegd bij het chunken.** Elke chunk hoort bij precies één sectie.
  Het kopjespad en de contextregel maken ook een korte chunk vindbaar, en samenvoegen zou de bron vertroebelen.
- **Full-text gebruikt een OR van de woorden van de vraag**, met `ts_rank_cd` voor de volgorde. De
  standaard-AND van `websearch_to_tsquery` vond bij natuurlijke vragen vrijwel niets.
- **Minimale vectorgelijkenis van 0,35** (`knowledge.search.min_similarity`). Zonder drempel vult het
  budget zich met irrelevante secties, en een vraag die niet in de kennisbank staat, levert niets op.
  Gemeten op het voorbeeld: relevant scoort ≥ 0,45, ruis ~0,36, onzin < 0,2.
  Met contextregels in de embedding scoort elke vraag uit hetzelfde domein ~0,38. Daarom moet een treffer
  die alleen via vectoren binnenkomt, ≥ 0,45 halen (`min_similarity_vector_only`).
- **Eerste evaluatie (echte API, voorbeeld-handboek, 25 vragen):** bron in de top 5 bij 100% van de vragen,
  MRR 0,91, gevraagd feit gevonden bij 100%, en de „niet in de kennisbank”-vragen leveren niets op.
  Gemiddeld ~270 tokens. Zonder embeddings (alleen tekst) was dat 84%. De verwerking van het hele document
  kostte ≈ $0,005.
- **Mapkoppelingen alleen op secties.** Feiten en documenten erven hun mappen van hun secties. Daardoor
  verandert er niets dubbel bij een nieuwe versie.
- **Bronverwijzingen van een mapoverzicht** staan in de bronnenlijst naast het overzicht, niet als nummers in
  de tekst. Die nummers zouden na elke herschrijving verschuiven.
- **Robuustheid (nieuw).** Een AI-stap die faalt, mag een document nooit onvindbaar maken:
    - contextregels en embeddings geven na de laatste poging op, en de keten loopt door;
    - na "doorzoekbaar" laat een fout de status staan;
    - opnieuw verwerken hergebruikt het eigen werk van de versie.
- **`retry_after` naar 960 seconden** op de database- en Redis-queue. Die moet groter zijn dan de timeout
  van 900 seconden, anders pakken twee workers dezelfde job op.
- **De evaluatieset telt nu 25 vragen** op het voorbeeld-handboek, in alle categorieën. Uitbreiden naar
  ~50 gebeurt met echte documenten (Q3).

**Nog open:**

- Q1 (EU-dataresidentie), Q2 (retentie) en Q3 (testdocumenten) uit hoofdstuk 0.
- Reranker (Voyage of Cohere): het haakpunt staat beschreven, er is nog niets gebouwd.
