<?php

namespace App\Http\Controllers\Mailboxes;

use App\Exceptions\MailboxNeedsReauthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mailboxes\MailboxImportRequest;
use App\Http\Requests\Mailboxes\MailboxSearchRequest;
use App\Models\Mailbox;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use App\Services\Gmail\MailboxImporter;
use App\Services\Gmail\StoreGmailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Search a mailbox live and import what is picked.
 *
 * Search results are never stored: they come straight from the Gmail API, and
 * only the messages someone chooses become Emails.
 */
class MailboxImportController extends Controller
{
    public function search(
        MailboxSearchRequest $request,
        Mailbox $mailbox,
        GmailMessageParser $parser,
        StoreGmailMessage $store,
    ): JsonResponse {
        abort_unless($mailbox->isActive(), 409);

        $client = GmailClient::for($mailbox);

        try {
            $page = $client->listMessages($request->gmailQuery(), $request->input('page_token'));
            $ids = array_map(fn (array $m): string => $m['id'], $page['messages'] ?? []);
            $summaries = $client->messageSummaries($ids);
        } catch (MailboxNeedsReauthException) {
            return response()->json(['message' => __('This mailbox has to be connected again.')], 409);
        }

        $imported = array_flip($store->existing($mailbox, $ids));

        $messages = [];

        foreach ($ids as $id) {
            if (! isset($summaries[$id])) {
                continue;
            }

            $parsed = $parser->parse($summaries[$id]);

            $messages[] = [
                'id' => $id,
                'threadId' => $parsed['provider_thread_id'],
                'fromName' => $parsed['from_name'],
                'fromEmail' => $parsed['from_email'],
                'subject' => $parsed['subject'],
                'snippet' => $parsed['snippet'],
                'receivedAt' => $parsed['received_at']?->toIso8601String(),
                'hasAttachments' => in_array('HAS_ATTACHMENT', $parsed['label_ids'], true)
                    || $this->hasAttachmentPart($summaries[$id]),
                'alreadyImported' => isset($imported[$id]),
            ];
        }

        return response()->json([
            'messages' => $messages,
            'nextPageToken' => $page['nextPageToken'] ?? null,
            'estimate' => $page['resultSizeEstimate'] ?? count($messages),
        ]);
    }

    public function store(MailboxImportRequest $request, Mailbox $mailbox, MailboxImporter $importer): RedirectResponse
    {
        abort_unless($mailbox->isActive(), 409);

        /** @var list<string> $ids */
        $ids = array_values($request->validated('message_ids'));

        try {
            $count = $importer->import($mailbox, $ids, $request->user()->id, $request->boolean('whole_threads'));
        } catch (MailboxNeedsReauthException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This mailbox has to be connected again.')]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 0
                ? __('Those emails were already imported.')
                : trans_choice('{1} Importing 1 email.|[2,*] Importing :count emails.', $count),
        ]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function hasAttachmentPart(array $summary): bool
    {
        $mime = $summary['payload']['mimeType'] ?? '';

        return is_string($mime) && str_starts_with($mime, 'multipart/mixed');
    }
}
