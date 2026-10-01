<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\FactStatus;
use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Models\Email;
use App\Models\KnowledgeFact;
use App\Services\Knowledge\Ai\AiGateway;
use Throwable;

/**
 * Writes statements from a mail as facts sourced from that mail, embedded so
 * search can find them once they count. Used when a mail is read (proposed
 * facts) and when a reviewer approves a statement that had none yet.
 */
class MailFactWriter
{
    public function __construct(private readonly AiGateway $ai) {}

    /**
     * @param  list<array{statement: string, subject: string|null, valid_from: string|null}>  $statements
     * @return list<KnowledgeFact> in the order given
     */
    public function write(Email $email, array $statements, FactStatus $status, ?string $language): array
    {
        $searchConfig = (Locale::tryFrom(strtolower((string) $language)) ?? Tenancy::account()->locale)->searchConfiguration();

        $facts = array_map(fn (array $statement): KnowledgeFact => KnowledgeFact::create([
            'source_type' => 'email',
            'source_id' => $email->id,
            'statement' => $statement['statement'],
            'subject' => $statement['subject'] === null ? null : mb_substr($statement['subject'], 0, 120),
            'status' => $status,
            'valid_from' => $statement['valid_from'] ?? $email->received_at?->toDateString(),
            'content_hash' => self::hash($statement['statement']),
            'search_config' => $searchConfig,
        ]), $statements);

        $this->embed($email, $facts);

        return $facts;
    }

    /**
     * The same normalisation as document facts, so a claim matches across both.
     */
    public static function hash(string $statement): string
    {
        return hash('sha256', mb_strtolower((string) preg_replace('/\s+/u', ' ', $statement)));
    }

    /**
     * Without embeddings a fact is still found by its words, so a provider
     * that is down or not configured never loses a fact.
     *
     * @param  list<KnowledgeFact>  $facts
     */
    private function embed(Email $email, array $facts): void
    {
        if ($facts === [] || ! $this->ai->enabled()) {
            return;
        }

        try {
            $vectors = $this->ai->embed(
                array_map(fn (KnowledgeFact $fact): string => trim(($fact->subject ? $fact->subject.': ' : '').$fact->statement), $facts),
                'embed',
                $email,
            );
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        foreach ($vectors as $index => $vector) {
            $facts[$index]->update(['embedding' => $vector, 'embedding_model' => config('knowledge.embeddings.model')]);
        }
    }
}
