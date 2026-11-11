<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Mailbox;
use App\Models\MailboxRule;
use App\Services\Gmail\MailboxImporter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mailboxes as the mailbox components show them, for the settings page and
 * for setup.
 */
trait PresentsMailboxes
{
    /**
     * Load what presentMailbox() reads.
     *
     * @param  Builder<Mailbox>  $query
     * @return Builder<Mailbox>
     */
    protected function withMailboxDetails(Builder $query): Builder
    {
        return $query
            ->withCount('emails')
            ->with(['rules' => fn ($query) => $query->oldest('id'), 'rules.colleague']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentMailbox(Mailbox $mailbox, MailboxImporter $importer, bool $isAdmin): array
    {
        $own = $mailbox->ownDomainRule();

        return [
            'id' => $mailbox->id,
            'emailAddress' => $mailbox->email_address,
            'provider' => $mailbox->provider->value,
            'status' => $mailbox->status->value,
            'lastMessageAt' => $mailbox->last_message_at?->toIso8601String(),
            'emailsCount' => $mailbox->emails_count,
            'import' => $importer->progress($mailbox),
            'domain' => $mailbox->domain(),
            'publicDomain' => $mailbox->isOnPublicDomain(),
            'domainAccess' => $mailbox->domainAccess()->value,
            'senderRules' => $mailbox->rules
                ->reject(fn (MailboxRule $rule): bool => $rule->is($own))
                ->map(fn (MailboxRule $rule): array => [
                    'entry' => $rule->entry(),
                    'access' => $rule->access->value,
                    'createsColleagues' => $rule->creates_colleagues,
                    'colleagueId' => $rule->colleague_id,
                ])
                ->values()
                ->all(),
            'importOffered' => $isAdmin && $mailbox->offersImport(),
        ];
    }

    /**
     * The numbers the explanation of a mailbox quotes, from where they are set.
     *
     * @return array{answerConfidence: int, aiLogDays: int}
     */
    protected function mailFacts(): array
    {
        return [
            'answerConfidence' => (int) round((float) config('knowledge.mail.answer_min_confidence') * 100),
            'aiLogDays' => config('knowledge.ai_log.payloads') ? (int) config('knowledge.ai_log.payload_retention_days') : 0,
        ];
    }
}
