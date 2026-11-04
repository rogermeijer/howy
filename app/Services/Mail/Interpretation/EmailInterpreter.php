<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\AttachmentDecision;
use App\Enums\EmailIntent;
use App\Enums\FactStatus;
use App\Enums\FingerprintUse;
use App\Enums\InterpretationMode;
use App\Enums\InterpretationOutcome;
use App\Enums\InterpretationStatus;
use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use App\Services\Knowledge\Ai\Agents\QuestionAnswerer;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\Facts\FactSources;
use App\Services\Knowledge\KnowledgeLanguage;
use App\Services\Knowledge\Review\FactReconciler;
use App\Services\Knowledge\Search\FactHit;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\Passage;
use App\Services\Knowledge\Search\SearchQuery;
use App\Services\Mail\MailAttachments;
use App\Services\Mail\MailFingerprints;
use App\Services\Mail\MailNovelty;
use App\Services\Mail\MailReading;
use App\Services\Mail\PreparedAttachments;
use App\Services\Mail\ThreadDocumentWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Reads one mail to a mailbox and acts on it, in the role the mailbox has.
 *
 * A light model sorts it first. A question is answered from the knowledge base
 * (or honestly not); information is split into statements, each checked
 * against the facts already known: new ones are added as facts sourced from
 * the mail, known ones are skipped, and contradicting ones are held.
 *
 * Written to the mailbox, Howy answers the sender when it is sure enough,
 * and explains a conflict. Only copied, it writes to no one and answers
 * nothing: it files what the people in the conversation say.
 * Every step is written to the interpretation, so the mail always shows how
 * it was read and what followed.
 *
 * Mail is read once. The same mail through a second mailbox is not read
 * again, and of a forward or a reply only what Howy has not read before is a
 * source; what it has read is context. A mail with nothing new is not sent
 * to a model at all.
 *
 * Attachments are documents like uploads. One the knowledge base already
 * holds gets this mail as one more source; a new one the classifier judges
 * to be knowledge goes in and waits for approval.
 *
 * @phpstan-import-type Citation from EmailInterpretation
 * @phpstan-import-type Statement from EmailInterpretation
 * @phpstan-import-type Quote from Email
 */
class EmailInterpreter
{
    /** Enough of a mail to judge it; a longer one is cut, not refused. */
    private const int MAX_PROMPT_CHARS = 12000;

    public function __construct(
        private readonly AiGateway $ai,
        private readonly KnowledgeSearch $search,
        private readonly ReplyComposer $composer,
        private readonly ThreadReplier $replier,
        private readonly EmailEligibility $eligibility,
        private readonly MailFactWriter $facts,
        private readonly MailNovelty $novelty,
        private readonly MailFingerprints $fingerprints,
        private readonly MailAttachments $attachments,
        private readonly FactReconciler $reconciler,
        private readonly ThreadDocumentWriter $threads,
        private readonly FactSources $sources,
    ) {}

    public function interpret(Email $email): EmailInterpretation
    {
        $interpretation = EmailInterpretation::query()->firstOrCreate(
            ['email_id' => $email->id],
            ['mode' => $this->eligibility->role($email, $email->mailbox)],
        );

        $twin = $this->readElsewhere($email, $interpretation);

        if ($twin !== null) {
            $interpretation->update([
                'status' => InterpretationStatus::Skipped,
                'error' => __('This mail was already read through :mailbox.', ['mailbox' => $twin->mailbox->email_address ?? '']),
                'processed_at' => now(),
            ]);

            return $interpretation;
        }

        $prepared = $this->attachments->prepare($email);

        try {
            return $this->read($email, $interpretation, $prepared);
        } finally {
            $prepared->cleanup();
        }
    }

    private function read(Email $email, EmailInterpretation $interpretation, PreparedAttachments $prepared): EmailInterpretation
    {
        $novelty = $this->novelty->read($email);

        if ($novelty->nothingNew() && ! $prepared->hasNew() && ! $this->isDirect($email, $interpretation)) {
            $interpretation->update([
                'status' => InterpretationStatus::Done,
                'intent' => null,
                // Said in the reader's language when shown (InterpretationPresenter).
                'outcome' => InterpretationOutcome::Duplicate,
                'summary' => null,
                'statements' => null,
                // Where it was read first, to look it up there.
                'citations' => Email::query()->whereKey($novelty->knownFrom)->whereKeyNot($email->id)->get(['id', 'subject'])->map(fn (Email $known): array => [
                    'type' => 'email', 'id' => $known->id, 'title' => (string) $known->subject, 'section_id' => null, 'heading_path' => null, 'page' => null,
                ])->values()->all(),
                'needs_review' => false,
                'error' => null,
                'processed_at' => now(),
            ]);

            return $interpretation;
        }

        if (! $this->ai->enabled()) {
            $interpretation->update([
                'status' => InterpretationStatus::Skipped,
                'error' => __('No AI provider is configured.'),
                'processed_at' => now(),
            ]);

            return $interpretation;
        }

        // A re-run starts from a clean reading: what it proposed before and
        // nobody approved goes. The reply fields stay: a reply that went out
        // once is never sent again.
        KnowledgeFact::query()
            ->where('source_type', 'email')
            ->where('source_id', $email->id)
            ->where('status', FactStatus::Proposed)
            ->delete();

        $interpretation->update([
            'status' => InterpretationStatus::Processing,
            'outcome' => null,
            'question' => null,
            'answer' => null,
            'answer_confidence' => null,
            'answer_gaps' => null,
            'citations' => null,
            'statements' => null,
            'needs_review' => false,
            'error' => null,
        ]);

        // History Howy has not read is a source: short, it is read with the
        // mail; long, it is filed as a document of its own.
        [$inline, $filed] = $this->history($email, $novelty);

        $reading = $this->ai->structured(new EmailClassifier, $this->mailPrompt($email, $novelty, $prepared, $this->historyPrompt($email, $inline, $filed), $this->earlier($email, $novelty)), 'mail_classify', $email);

        $intent = EmailIntent::tryFrom((string) ($reading['intent'] ?? '')) ?? EmailIntent::Other;
        $language = Str::limit(strtolower(trim((string) ($reading['language'] ?? ''))), 10, '');

        $interpretation->fill([
            'intent' => $intent,
            'intent_confidence' => max(0, min(1, (float) ($reading['confidence'] ?? 0))),
            'summary' => trim((string) ($reading['summary'] ?? '')) ?: null,
            'language' => $language !== '' ? $language : null,
        ]);

        $locale = $this->composer->locale($language, Tenancy::account()->locale);

        match ($intent) {
            // Copied, a question is for the people it was put to, not for Howy.
            EmailIntent::Question => $interpretation->mode === InterpretationMode::Copied
                ? $interpretation->outcome = InterpretationOutcome::NoAction
                : $this->answer($interpretation, $email, $reading, $locale),
            EmailIntent::Information => $this->file($interpretation, $email, $reading, $locale),
            EmailIntent::Other => $interpretation->outcome = InterpretationOutcome::NoAction,
        };

        // Whatever the mail is about, its attachments can be knowledge.
        $this->attachments->apply($email, $prepared, (array) ($reading['attachments'] ?? []), $interpretation->summary, $interpretation->language);

        // A mail that only sends a file still proposed something, or knew it.
        if ($interpretation->outcome === InterpretationOutcome::NoAction) {
            $decisions = array_column($email->attachments ?? [], 'decision');

            $interpretation->outcome = match (true) {
                in_array(AttachmentDecision::New->value, $decisions, true) => InterpretationOutcome::Added,
                in_array(AttachmentDecision::Known->value, $decisions, true) => InterpretationOutcome::Duplicate,
                default => InterpretationOutcome::NoAction,
            };
        }

        $interpretation->fill(['status' => InterpretationStatus::Done, 'processed_at' => now()])->save();

        // Read: from now on this text is recognised wherever it turns up.
        $this->fingerprints->record($email, (string) ($email->content_text ?? $email->body_text ?? ''), FingerprintUse::Read);

        foreach ($filed !== null ? $novelty->unseen : $inline as $quote) {
            $this->fingerprints->record($email, $quote['text'], $filed !== null ? FingerprintUse::Document : FingerprintUse::Statements, $filed?->id);
        }

        return $interpretation;
    }

    /**
     * What to do with history Howy has not read: read it with the mail when
     * it is short, file it as a document when it (or the mail itself) is too
     * long to read as a mail.
     *
     * @return array{0: list<Quote>, 1: DocumentVersion|null}
     */
    private function history(Email $email, MailReading $novelty): array
    {
        $length = array_sum(array_map(fn (array $quote): int => mb_strlen($quote['text']), $novelty->unseen));
        $longBody = mb_strlen((string) ($email->content_text ?? $email->body_text ?? '')) > (int) config('knowledge.mail.document_min_chars');

        if ($length > (int) config('knowledge.mail.history_inline_chars') || $longBody) {
            return [[], $this->threads->write($email, $novelty->unseen, $longBody, null)];
        }

        return [$novelty->unseen, null];
    }

    /**
     * @param  list<Quote>  $inline
     */
    private function historyPrompt(Email $email, array $inline, ?DocumentVersion $filed): ?string
    {
        if ($filed !== null) {
            return "<history filed=\"document\">\nThe earlier messages of this thread that you have not read are filed as a separate document.\n</history>";
        }

        if ($inline === []) {
            return null;
        }

        $forward = preg_match('/^\s*(?:fwd?|fw|doorst|wg|tr|rv)\s*:/iu', (string) $email->subject) === 1
            || collect($inline)->contains(fn (array $quote): bool => $quote['forwarded'] ?? false);
        $tag = $forward ? 'forwarded' : 'history';

        // Oldest first, with the sender's name and the date only.
        $messages = array_map(fn (array $quote): string => '<message from="'.e($quote['name'] ?? '').'"'.($quote['date'] !== null ? ' date="'.substr($quote['date'], 0, 10).'"' : '').">\n"
            .$this->mask(trim($quote['text']))."\n</message>", array_reverse($inline));

        return "<{$tag}>\n".implode("\n", $messages)."\n</{$tag}>";
    }

    /**
     * The same message (by its Message-ID), already read through another of
     * the account's mailboxes in at least the role it has here: copied twice
     * is read once, but a question addressed here is still answered.
     */
    private function readElsewhere(Email $email, EmailInterpretation $interpretation): ?Email
    {
        if ($email->message_id_header === null) {
            return null;
        }

        return Email::query()
            ->with('mailbox')
            ->where('message_id_header', $email->message_id_header)
            ->where('mailbox_id', '!=', $email->mailbox_id)
            ->whereHas('interpretation', fn (Builder $query) => $query
                ->where('status', InterpretationStatus::Done)
                ->when($interpretation->mode === InterpretationMode::Addressed, fn (Builder $query) => $query->where('mode', InterpretationMode::Addressed)))
            ->first();
    }

    /**
     * A new mail written to Howy itself, not a reply or a forward: whoever
     * asks again deserves an answer again.
     */
    private function isDirect(Email $email, EmailInterpretation $interpretation): bool
    {
        return $interpretation->mode === InterpretationMode::Addressed
            && $email->in_reply_to === null
            && ($email->quotes ?? []) === [];
    }

    /**
     * @param  array<string, mixed>  $reading
     */
    private function answer(EmailInterpretation $interpretation, Email $email, array $reading, string $locale): void
    {
        $question = trim((string) ($reading['question'] ?? '')) ?: trim((string) $email->subject);
        $interpretation->question = $question;

        $result = $this->search->search(new SearchQuery(
            $question,
            maxTokens: (int) config('knowledge.mail.answer_max_tokens'),
            language: $this->searchLanguage($locale),
        ));

        /** @var array<string, array{text: string, citation: Citation}> $sources */
        $sources = [];

        foreach ($result->facts as $index => $fact) {
            $sources['F'.($index + 1)] = ['text' => $fact->statement, 'citation' => $this->factCitation($fact)];
        }

        foreach ($result->passages as $index => $passage) {
            $sources['S'.($index + 1)] = ['text' => $passage->text, 'citation' => $this->passageCitation($passage)];
        }

        $response = $sources === [] ? ['answered' => false] : $this->ai->structured(
            new QuestionAnswerer,
            $this->answerPrompt($question, $sources, $locale),
            'mail_answer',
            $email,
        );

        $answer = trim((string) ($response['answer'] ?? ''));

        if (($response['answered'] ?? false) !== true || $answer === '') {
            $interpretation->outcome = InterpretationOutcome::NotFound;
            $interpretation->save();
            $this->replier->reply($interpretation, $email, $this->composer->notFound($question, $locale));

            return;
        }

        /** @var list<string> $cited */
        $cited = array_values(array_filter((array) ($response['sources'] ?? []), 'is_string'));
        $citations = $this->unique(array_map(
            fn (string $id): array => $sources[$id]['citation'],
            array_values(array_filter($cited, fn (string $id): bool => isset($sources[$id]))),
        ));
        $confidence = max(0, min(1, (float) ($response['confidence'] ?? 0)));
        $gaps = trim((string) ($response['missing'] ?? '')) ?: null;

        // Only an answer Howy is sure enough of goes out; a less sure one is
        // kept on the mail, for the people in the conversation to judge.
        $sure = $confidence >= (float) config('knowledge.mail.answer_min_confidence');

        $interpretation->fill([
            'outcome' => $sure ? InterpretationOutcome::Answered : InterpretationOutcome::Unsure,
            'answer' => $answer,
            'answer_confidence' => $confidence,
            'answer_gaps' => $gaps,
            'citations' => $citations,
        ])->save();

        if ($sure) {
            $this->replier->reply($interpretation, $email, $this->composer->answer($question, $answer, $citations, $gaps, $locale));
        }
    }

    /**
     * @param  array<string, mixed>  $reading
     */
    private function file(EmailInterpretation $interpretation, Email $email, array $reading, string $locale): void
    {
        $statements = $this->statements($reading);

        if ($statements === []) {
            $interpretation->outcome = InterpretationOutcome::NoAction;

            return;
        }

        /** @var array<string, array{statement: array{statement: string, subject: string|null, valid_from: string|null, flag: string|null, from_history: bool}, hash: string, candidates: list<KnowledgeFact>}> $open */
        $open = [];
        /** @var list<Statement> $judged */
        $judged = [];

        foreach ($statements as $index => $statement) {
            $hash = MailFactWriter::hash($statement['statement']);
            $known = KnowledgeFact::query()
                ->where('content_hash', $hash)
                ->where('status', '!=', FactStatus::Expired)
                ->orderByRaw("case when source_type = 'email' and source_id = ? then 0 else 1 end", [$email->id])
                ->first();

            if ($known !== null) {
                // Ours from an earlier run of this same mail: still new, not a duplicate.
                $ours = $known->source_type === 'email' && $known->source_id === $email->id;
                $judged[$index] = $this->judged($statement, $ours ? 'new' : 'duplicate', $ours ? $known->id : null, $ours ? null : $known);

                continue;
            }

            $candidates = $this->candidates($statement['statement']);

            if ($candidates === []) {
                $judged[$index] = $this->judged($statement, 'new');

                continue;
            }

            $open['N'.($index + 1)] = ['statement' => $statement, 'hash' => $hash, 'candidates' => $candidates];
        }

        if ($open !== []) {
            $claims = array_map(fn (array $item): array => [
                'statement' => $item['statement']['statement'],
                'valid_from' => $item['statement']['valid_from'],
                'candidates' => $item['candidates'],
            ], $open);

            foreach ($this->reconciler->check($claims, $locale, $email, 'mail_conflicts') as $id => $judgement) {
                $judged[(int) substr($id, 1) - 1] = $this->judged($open[$id]['statement'], $judgement['verdict'], null, $judgement['existing'], $judgement['explanation']);
            }
        }

        ksort($judged);
        $judged = array_values($judged);
        $judged = $this->addNewFacts($judged, $email);
        $this->confirmKnown($judged, $email);

        $verdicts = array_column($judged, 'verdict');
        $interpretation->statements = $judged;
        $interpretation->needs_review = in_array('pending', array_column($judged, 'review'), true);
        $interpretation->outcome = match (true) {
            in_array('conflict', $verdicts, true) => InterpretationOutcome::Conflict,
            in_array('new', $verdicts, true) => InterpretationOutcome::Added,
            default => InterpretationOutcome::Duplicate,
        };
        $interpretation->save();

        // Copied, Howy never writes: the conflict is only flagged.
        if ($interpretation->outcome === InterpretationOutcome::Conflict && $interpretation->mode === InterpretationMode::Addressed) {
            $this->replier->reply($interpretation, $email, $this->composer->conflict($judged, $this->conflictSources($judged), $locale));
        }
    }

    /**
     * Store the new statements as proposed facts sourced from the mail: kept
     * and embedded, but not searched until someone approves them.
     *
     * @param  list<Statement>  $judged
     * @return list<Statement>
     */
    private function addNewFacts(array $judged, Email $email): array
    {
        $new = array_filter($judged, fn (array $statement): bool => $statement['verdict'] === 'new' && $statement['fact_id'] === null);
        $facts = $this->facts->write($email, array_values($new), FactStatus::Proposed);

        $ids = [];

        foreach (array_keys($new) as $position => $index) {
            $ids[$index] = $facts[$position]->id;
        }

        return array_map(
            fn (array $statement, int $index): array => isset($ids[$index]) ? [...$statement, 'fact_id' => $ids[$index]] : $statement,
            $judged,
            array_keys($judged),
        );
    }

    /**
     * What the mail says that is known already: the mail is one more source
     * of it. It changes nothing that is known, so it waits for nobody.
     *
     * @param  list<Statement>  $judged
     */
    private function confirmKnown(array $judged, Email $email): void
    {
        $ids = array_values(array_filter(array_map(
            fn (array $statement): ?int => $statement['verdict'] === 'duplicate' ? $statement['existing_fact_id'] : null,
            $judged,
        )));

        foreach (KnowledgeFact::query()->whereKey($ids)->get() as $known) {
            if ($this->sources->canConfirm($known)) {
                $this->sources->confirmByMail($known, $email);
            }
        }
    }

    /**
     * The facts most like a statement, as the conflict check's comparison set:
     * published, and pending in another mail or document, so the same claim
     * is not proposed twice.
     *
     * @return list<KnowledgeFact>
     */
    private function candidates(string $statement): array
    {
        $hits = array_slice(
            $this->search->search(new SearchQuery(
                $statement,
                factStatuses: [FactStatus::Core, FactStatus::Supplementary, FactStatus::Proposed],
                maxTokens: 500,
                // Statements and facts are in the knowledge base's language.
                language: KnowledgeLanguage::searchConfiguration(),
            ))->facts,
            0,
            (int) config('knowledge.mail.conflict_candidates'),
        );

        if ($hits === []) {
            return [];
        }

        $facts = KnowledgeFact::query()->whereKey(array_map(fn (FactHit $hit): int => $hit->id, $hits))->get()->keyBy('id');

        return array_values(array_filter(array_map(fn (FactHit $hit): ?KnowledgeFact => $facts->get($hit->id), $hits)));
    }

    /**
     * @param  array{statement: string, subject: string|null, valid_from: string|null, flag: string|null, from_history?: bool}  $statement
     * @param  'new'|'duplicate'|'conflict'  $verdict
     * @return Statement
     */
    private function judged(array $statement, string $verdict, ?int $factId = null, ?KnowledgeFact $existing = null, ?string $explanation = null): array
    {
        return [
            'statement' => $statement['statement'],
            'subject' => $statement['subject'],
            'valid_from' => $statement['valid_from'],
            'verdict' => $verdict,
            'fact_id' => $factId,
            'existing_fact_id' => $existing?->id,
            'existing_statement' => $existing?->statement,
            'explanation' => filled($explanation) ? $explanation : null,
            'flag' => $statement['flag'],
            'from_history' => $statement['from_history'] ?? false,
            // What would change the knowledge base waits for a person.
            'review' => in_array($verdict, ['new', 'conflict'], true) ? 'pending' : null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    /**
     * Where each conflicting fact comes from, to cite it in the reply.
     *
     * @param  list<Statement>  $judged
     * @return array<int, Citation>
     */
    private function conflictSources(array $judged): array
    {
        $ids = array_values(array_filter(array_map(
            fn (array $statement): ?int => $statement['verdict'] === 'conflict' ? $statement['existing_fact_id'] : null,
            $judged,
        )));

        $sources = [];

        foreach (KnowledgeFact::query()->with(['document', 'section'])->whereKey($ids)->get() as $fact) {
            $sources[$fact->id] = $fact->source_type === 'email'
                ? ['type' => 'email', 'id' => $fact->source_id, 'title' => (string) Email::query()->whereKey($fact->source_id)->value('subject'), 'section_id' => null, 'heading_path' => null, 'page' => null]
                : ['type' => 'document', 'id' => (int) $fact->document_id, 'title' => $fact->documentTitle(), 'section_id' => $fact->section_id, 'heading_path' => $fact->section?->heading_path, 'page' => $fact->page_from];
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $reading
     * @return list<array{statement: string, subject: string|null, valid_from: string|null, flag: string|null, from_history: bool}>
     */
    private function statements(array $reading): array
    {
        $statements = [];
        $seen = [];

        foreach ((array) ($reading['statements'] ?? []) as $statement) {
            $text = is_array($statement) ? trim((string) ($statement['statement'] ?? '')) : '';
            $hash = MailFactWriter::hash($text);

            if ($text === '' || isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;
            $subject = mb_strtolower(trim((string) ($statement['subject'] ?? '')));
            $validFrom = $statement['valid_from'] ?? null;

            $flag = trim((string) ($statement['flag'] ?? ''));

            $statements[] = [
                'statement' => $text,
                'subject' => $subject !== '' ? $subject : null,
                'flag' => $flag !== '' ? $flag : null,
                'from_history' => (bool) ($statement['from_history'] ?? false),
                'valid_from' => is_string($validFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom) === 1 && strtotime($validFrom) !== false ? $validFrom : null,
            ];
        }

        return $statements;
    }

    /**
     * The message a mail replies to, as context: the stored message it names
     * in In-Reply-To, else the text it quotes. When Howy told the thread what
     * the knowledge base does not know, that comes along: a reply that fills
     * the gap is exactly what should be learned.
     */
    private function earlier(Email $email, MailReading $novelty): ?string
    {
        $previous = $email->in_reply_to === null ? null : Email::query()
            ->with('interpretation')
            ->where('mailbox_id', $email->mailbox_id)
            ->where('message_id_header', $email->in_reply_to)
            ->first();

        // A quote not read before is history, given as a source instead.
        $text = trim((string) ($previous !== null
            ? ($previous->content_text ?? $previous->body_text)
            : ($novelty->known[0]['text'] ?? null)));

        $gaps = $previous->interpretation->answer_gaps ?? $this->threadGaps($email);
        $context = trim(($text !== '' ? Str::limit($text, (int) config('knowledge.mail.context_max_chars')) : '')
            .($gaps !== null ? "\n\n<missing>{$gaps}</missing>" : ''));

        return $context !== '' ? $context : null;
    }

    /**
     * What the knowledge base was last found to be missing in this thread,
     * for a reply to a message that is not stored (Howy's own reply).
     */
    private function threadGaps(Email $email): ?string
    {
        return EmailInterpretation::query()
            ->whereNotNull('answer_gaps')
            ->whereHas('email', fn (Builder $query) => $query
                ->where('mailbox_id', $email->mailbox_id)
                ->where('provider_thread_id', $email->provider_thread_id)
                ->whereKeyNot($email->id))
            ->latest('id')
            ->value('answer_gaps');
    }

    /**
     * The mail as the model sees it: subject, new text and the message it
     * replies to. No envelope beyond whether it was written to the mailbox
     * alone, and addresses in the text are masked, as AiGateway promises.
     */
    private function mailPrompt(Email $email, MailReading $novelty, PreparedAttachments $prepared, ?string $history, ?string $earlier = null): string
    {
        // What it says that was read before is left out, marked […].
        $text = $novelty->remaining > 0 ? $novelty->text : ($email->content_text ?? $email->body_text ?? $email->snippet ?? '');
        $prompt = 'Knowledge base language: '.KnowledgeLanguage::locale()->label().' ('.KnowledgeLanguage::locale()->value.")\n"
            .($this->eligibility->writtenToAlone($email, $email->mailbox) ? "Written to: the knowledge mailbox alone\n" : '')
            ."\n<subject>\n".trim((string) $email->subject)."\n</subject>\n\n<mail>\n".Str::limit(trim($this->mask($text)), self::MAX_PROMPT_CHARS)."\n</mail>";

        $attachments = $this->attachments->prompt($email, $prepared);
        $prompt .= $attachments === null ? '' : "\n\n{$attachments}";
        $prompt .= $history === null ? '' : "\n\n{$history}";

        return $earlier === null ? $prompt : $prompt."\n\n<earlier>\n".$this->mask($earlier)."\n</earlier>";
    }

    private function mask(string $text): string
    {
        return (string) preg_replace('/[^\s<>@]+@[^\s<>@]+\.[a-z]{2,}/i', '[email]', $text);
    }

    /**
     * @param  array<string, array{text: string, citation: Citation}>  $sources
     */
    private function answerPrompt(string $question, array $sources, string $locale): string
    {
        $blocks = [];

        foreach ($sources as $id => $source) {
            $blocks[] = "<source id=\"{$id}\" title=\"".e($source['citation']['title'])."\">\n{$source['text']}\n</source>";
        }

        return 'Answer in: '.(Locale::tryFrom($locale)?->label() ?? $locale)."\n\n<question>\n{$question}\n</question>\n\n".implode("\n\n", $blocks);
    }

    /**
     * @return Citation
     */
    private function passageCitation(Passage $passage): array
    {
        return [
            'type' => 'document',
            'id' => $passage->documentId,
            'title' => $passage->documentTitle,
            'section_id' => $passage->sectionId,
            'heading_path' => $passage->headingPath !== '' ? $passage->headingPath : null,
            'page' => $passage->pageFrom,
        ];
    }

    /**
     * @return Citation
     */
    private function factCitation(FactHit $fact): array
    {
        if ($fact->emailId !== null) {
            return ['type' => 'email', 'id' => $fact->emailId, 'title' => (string) $fact->emailSubject, 'section_id' => null, 'heading_path' => null, 'page' => null];
        }

        return [
            'type' => 'document',
            'id' => (int) $fact->documentId,
            'title' => (string) $fact->documentTitle,
            'section_id' => $fact->sectionId,
            'heading_path' => $fact->headingPath,
            'page' => $fact->pageFrom,
        ];
    }

    /**
     * One citation per place: two passages of one section cite it once.
     *
     * @param  list<Citation>  $citations
     * @return list<Citation>
     */
    private function unique(array $citations): array
    {
        $unique = [];

        foreach ($citations as $citation) {
            $unique[$citation['type'].':'.$citation['id'].':'.($citation['section_id'] ?? '')] ??= $citation;
        }

        return array_values($unique);
    }

    private function searchLanguage(string $locale): string
    {
        return (Locale::tryFrom($locale) ?? Tenancy::account()->locale)->searchConfiguration();
    }
}
