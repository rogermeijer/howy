<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\EmailIntent;
use App\Enums\FactStatus;
use App\Enums\InterpretationMode;
use App\Enums\InterpretationOutcome;
use App\Enums\InterpretationStatus;
use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\KnowledgeFact;
use App\Services\Knowledge\Ai\Agents\EmailClassifier;
use App\Services\Knowledge\Ai\Agents\FactConflictChecker;
use App\Services\Knowledge\Ai\Agents\QuestionAnswerer;
use App\Services\Knowledge\Ai\AiGateway;
use App\Services\Knowledge\Search\FactHit;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\Passage;
use App\Services\Knowledge\Search\SearchQuery;
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
 * Written to the mailbox, Howy answers the sender and explains a conflict.
 * Only copied, it never writes to the sender: it suggests an answer to the
 * people who were asked, when it is sure enough, and files what they answer.
 * Every step is written to the interpretation, so the mail always shows how
 * it was read and what followed.
 *
 * @phpstan-import-type Citation from EmailInterpretation
 * @phpstan-import-type Statement from EmailInterpretation
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
    ) {}

    public function interpret(Email $email): EmailInterpretation
    {
        $interpretation = EmailInterpretation::query()->firstOrCreate(
            ['email_id' => $email->id],
            ['mode' => $this->eligibility->role($email, $email->mailbox)],
        );

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

        $reading = $this->ai->structured(new EmailClassifier, $this->mailPrompt($email, $this->earlier($email)), 'mail_classify', $email);

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
            EmailIntent::Question => $this->answer($interpretation, $email, $reading, $locale),
            EmailIntent::Information => $this->file($interpretation, $email, $reading, $locale),
            EmailIntent::Other => $interpretation->outcome = InterpretationOutcome::NoAction,
        };

        $interpretation->fill(['status' => InterpretationStatus::Done, 'processed_at' => now()])->save();

        return $interpretation;
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
        $copied = $interpretation->mode === InterpretationMode::Copied;

        if (($response['answered'] ?? false) !== true || $answer === '') {
            $interpretation->outcome = InterpretationOutcome::NotFound;
            $interpretation->save();

            // Copied, Howy only speaks up when it has something to offer.
            if (! $copied) {
                $this->replier->reply($interpretation, $email, $this->composer->notFound($question, $locale));
            }

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

        $interpretation->fill([
            'outcome' => InterpretationOutcome::Answered,
            'answer' => $answer,
            'answer_confidence' => $confidence,
            'answer_gaps' => $gaps,
            'citations' => $citations,
        ]);

        if (! $copied) {
            $interpretation->save();
            $this->replier->reply($interpretation, $email, $this->composer->answer($question, $answer, $citations, $gaps, $locale));

            return;
        }

        // A question to someone else: suggest the answer to them, and only
        // when it is sure enough to be worth their attention.
        $asked = $this->replier->askedOf($email);

        $interpretation->outcome = match (true) {
            $asked === [] => InterpretationOutcome::NoAction,
            $confidence < (float) config('knowledge.mail.suggestion_min_confidence') => InterpretationOutcome::Unsure,
            default => InterpretationOutcome::Suggested,
        };
        $interpretation->save();

        if ($interpretation->outcome === InterpretationOutcome::Suggested) {
            $asker = $email->from_name ?? $email->from_email ?? '';
            $this->replier->suggest($interpretation, $email, $this->composer->suggestion($asker, $email->received_at, $question, $answer, $citations, $gaps, $confidence, $locale));
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

        /** @var array<string, array{statement: array{statement: string, subject: string|null, valid_from: string|null, flag: string|null}, hash: string, candidates: list<KnowledgeFact>}> $open */
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

            $candidates = $this->candidates($statement['statement'], $locale);

            if ($candidates === []) {
                $judged[$index] = $this->judged($statement, 'new');

                continue;
            }

            $open['N'.($index + 1)] = ['statement' => $statement, 'hash' => $hash, 'candidates' => $candidates];
        }

        if ($open !== []) {
            $response = $this->ai->structured(new FactConflictChecker, $this->conflictPrompt($open, $locale), 'mail_conflicts', $email);

            /** @var list<array{id?: string, verdict?: string, existing_fact_id?: int|null, explanation?: string}> $verdicts */
            $verdicts = is_array($response['verdicts'] ?? null) ? $response['verdicts'] : [];
            $byId = [];

            foreach ($verdicts as $verdict) {
                $byId[(string) ($verdict['id'] ?? '')] = $verdict;
            }

            foreach ($open as $id => $item) {
                $index = (int) substr($id, 1) - 1;
                $verdict = $byId[$id] ?? [];
                $existing = collect($item['candidates'])->firstWhere('id', (int) ($verdict['existing_fact_id'] ?? 0));

                // A verdict that points at no fact we offered cannot be acted on.
                $judged[$index] = match ($existing === null ? 'new' : ($verdict['verdict'] ?? 'new')) {
                    'duplicate' => $this->judged($item['statement'], 'duplicate', null, $existing),
                    'contradicts' => $this->judged($item['statement'], 'conflict', null, $existing, trim((string) ($verdict['explanation'] ?? ''))),
                    default => $this->judged($item['statement'], 'new'),
                };
            }
        }

        ksort($judged);
        $judged = array_values($judged);
        $judged = $this->addNewFacts($judged, $email, $locale);

        $verdicts = array_column($judged, 'verdict');
        $interpretation->statements = $judged;
        $interpretation->needs_review = in_array('pending', array_column($judged, 'review'), true);
        $interpretation->outcome = match (true) {
            in_array('conflict', $verdicts, true) => InterpretationOutcome::Conflict,
            in_array('new', $verdicts, true) => InterpretationOutcome::Added,
            default => InterpretationOutcome::Duplicate,
        };
        $interpretation->save();

        // Copied, Howy never writes to the sender: the conflict is only flagged.
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
    private function addNewFacts(array $judged, Email $email, ?string $language): array
    {
        $new = array_filter($judged, fn (array $statement): bool => $statement['verdict'] === 'new' && $statement['fact_id'] === null);
        $facts = $this->facts->write($email, array_values($new), FactStatus::Proposed, $language);

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
     * The facts most like a statement, as the conflict check's comparison set.
     *
     * @return list<KnowledgeFact>
     */
    private function candidates(string $statement, string $locale): array
    {
        $hits = array_slice(
            $this->search->search(new SearchQuery($statement, maxTokens: 500, language: $this->searchLanguage($locale)))->facts,
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
     * @param  array{statement: string, subject: string|null, valid_from: string|null, flag: string|null}  $statement
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
                : ['type' => 'document', 'id' => (int) $fact->document_id, 'title' => (string) $fact->document?->title, 'section_id' => $fact->section_id, 'heading_path' => $fact->section?->heading_path, 'page' => $fact->page_from];
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $reading
     * @return list<array{statement: string, subject: string|null, valid_from: string|null, flag: string|null}>
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
    private function earlier(Email $email): ?string
    {
        $previous = $email->in_reply_to === null ? null : Email::query()
            ->with('interpretation')
            ->where('mailbox_id', $email->mailbox_id)
            ->where('message_id_header', $email->in_reply_to)
            ->first();

        $text = trim((string) ($previous !== null
            ? ($previous->content_text ?? $previous->body_text)
            : ($email->quotes[0]['text'] ?? null)));

        $gaps = $previous->interpretation->answer_gaps ?? $this->threadGaps($email);
        $context = trim(($text !== '' ? Str::limit($text, (int) config('knowledge.mail.context_max_chars')) : '')
            .($gaps !== null ? "\n\n<missing>{$gaps}</missing>" : ''));

        return $context !== '' ? $context : null;
    }

    /**
     * What the knowledge base was last found to be missing in this thread,
     * for a reply to a message that is not stored (Howy's own suggestion).
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
     * replies to. No envelope, and addresses in the text are masked, as
     * AiGateway promises.
     */
    private function mailPrompt(Email $email, ?string $earlier = null): string
    {
        $text = $email->content_text ?? $email->body_text ?? $email->snippet ?? '';
        $prompt = "<subject>\n".trim((string) $email->subject)."\n</subject>\n\n<mail>\n".Str::limit(trim($this->mask($text)), self::MAX_PROMPT_CHARS)."\n</mail>";

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
     * @param  array<string, array{statement: array{statement: string, subject: string|null, valid_from: string|null, flag: string|null}, hash: string, candidates: list<KnowledgeFact>}>  $open
     */
    private function conflictPrompt(array $open, string $locale): string
    {
        $blocks = [];

        foreach ($open as $id => $item) {
            $facts = array_map(
                fn (KnowledgeFact $fact): string => "  <fact id=\"{$fact->id}\"".($fact->valid_from ? " valid_from=\"{$fact->valid_from->toDateString()}\"" : '').">{$fact->statement}</fact>",
                $item['candidates'],
            );

            $blocks[] = "<statement id=\"{$id}\"".($item['statement']['valid_from'] ? " valid_from=\"{$item['statement']['valid_from']}\"" : '').">\n  {$item['statement']['statement']}\n".implode("\n", $facts)."\n</statement>";
        }

        return 'Explain in: '.(Locale::tryFrom($locale)?->label() ?? $locale)."\n\n".implode("\n\n", $blocks);
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
