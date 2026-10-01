<?php

namespace App\Services\Gmail;

use App\Jobs\ImportGmailMessages;
use App\Models\Mailbox;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

/**
 * Queues a set of Gmail messages for import as one trackable batch.
 *
 * There is no imports table: the batch is the import. Its id goes on the mailbox
 * and the settings page reads progress straight from job_batches.
 */
class MailboxImporter
{
    private const int CHUNK = 10;

    public function __construct(private readonly StoreGmailMessage $store) {}

    /**
     * @param  list<string>  $messageIds
     * @return int how many messages were queued
     */
    public function import(Mailbox $mailbox, array $messageIds, ?int $userId, bool $wholeThreads = false): int
    {
        if ($wholeThreads) {
            $messageIds = $this->expandThreads($mailbox, $messageIds);
        }

        $ids = array_values(array_diff(
            array_values(array_unique($messageIds)),
            $this->store->existing($mailbox, $messageIds),
        ));

        if ($ids === []) {
            return 0;
        }

        $jobs = array_map(
            fn (array $chunk) => new ImportGmailMessages($mailbox->id, $chunk, $userId),
            array_chunk($ids, self::CHUNK),
        );

        $batch = Bus::batch($jobs)
            ->name("Import into mailbox {$mailbox->id}")
            ->allowFailures()
            ->withOption('messages', count($ids))
            ->dispatch();

        $mailbox->forceFill(['import_batch_id' => $batch->id])->save();

        return count($ids);
    }

    /**
     * Progress of the mailbox's current import, or null when none is running.
     *
     * @return array{processed: int, total: int}|null
     */
    public function progress(Mailbox $mailbox): ?array
    {
        if ($mailbox->import_batch_id === null) {
            return null;
        }

        $batch = Bus::findBatch($mailbox->import_batch_id);

        if (! $batch instanceof Batch || $batch->finished() || $batch->cancelled()) {
            return null;
        }

        // Progress is tracked per job; the message count is carried as an option
        // so the bar can speak in messages without a table of its own.
        $total = (int) ($batch->options['messages'] ?? $batch->totalJobs * self::CHUNK);

        return [
            'processed' => min($total, $batch->processedJobs() * self::CHUNK),
            'total' => $total,
        ];
    }

    /**
     * @param  list<string>  $messageIds
     * @return list<string>
     */
    private function expandThreads(Mailbox $mailbox, array $messageIds): array
    {
        $client = GmailClient::for($mailbox);
        $summaries = $client->messageSummaries($messageIds);

        $threads = [];

        foreach ($messageIds as $id) {
            $threads[(string) ($summaries[$id]['threadId'] ?? '')] = true;
        }

        unset($threads['']);

        $all = $messageIds;

        foreach (array_keys($threads) as $threadId) {
            array_push($all, ...$client->threadMessageIds((string) $threadId));
        }

        return $all;
    }
}
