<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Mailbox;
use App\Models\MailboxSender;
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
            ->with(['senders' => fn ($query) => $query->oldest('id')]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentMailbox(Mailbox $mailbox, MailboxImporter $importer, bool $isAdmin): array
    {
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
            'domainAccess' => $mailbox->domain_access->value,
            'senderRules' => $mailbox->senders->map(fn (MailboxSender $sender): array => [
                'entry' => $sender->entry,
                'access' => $sender->access->value,
            ])->all(),
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
