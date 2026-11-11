<?php

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Services\Mail\Interpretation\InterpretationPresenter;
use App\Services\Mail\MailNovelty;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    /**
     * Show an email within its whole conversation, newest message first.
     *
     * Typed binding, so another account's email 404s through the tenant scope.
     */
    public function show(Request $request, Email $email, InterpretationPresenter $presenter, MailNovelty $novelty): Response
    {
        $thread = Email::query()
            ->with(['interpretation', 'outboundMail'])
            ->where('mailbox_id', $email->mailbox_id)
            ->where('provider_thread_id', $email->provider_thread_id)
            ->latest('received_at')
            ->latest('id')
            ->get();

        $oldest = $thread->last();

        // The documents the thread's attachments are, to show where each went.
        $versions = DocumentVersion::query()
            ->with('document')
            ->whereKey($thread->flatMap(fn (Email $item): array => array_column($item->attachments ?? [], 'document_version_id'))->filter()->unique())
            ->get()
            ->keyBy('id');
        $participants = $thread->reverse()
            ->map(fn (Email $item): ?string => $item->from_name ?? $item->from_email)
            ->filter()
            ->unique()
            ->values();

        return Inertia::render('emails/show', [
            'currentId' => $email->id,
            // Only the account's admins decide what goes into the knowledge base.
            'canReview' => $request->user()->isAdminOf(Tenancy::account()),
            'subject' => $oldest->subject ?? $email->subject,
            'mailbox' => $email->mailbox?->email_address,
            'participants' => $participants,
            'messages' => $thread->map(fn (Email $item): array => [
                'id' => $item->id,
                'fromName' => $item->from_name,
                'fromEmail' => $item->from_email,
                'to' => $item->to ?? [],
                'cc' => $item->cc ?? [],
                'receivedAt' => $item->received_at?->toIso8601String(),
                'contentHtml' => $item->content_html,
                // Mail without HTML, or stored before extraction existed.
                'contentText' => $item->content_text ?? $item->body_text,
                'interpretation' => $item->interpretation !== null ? $presenter->full($item->interpretation) : null,
                // A reply to one of Howy's own mails says to which.
                'repliesTo' => $item->outboundMail !== null ? [
                    'kind' => $item->outboundMail->kind->value,
                    'label' => $item->outboundMail->kind->label(),
                    'sentAt' => $item->outboundMail->sent_at?->toIso8601String(),
                ] : null,
                'excerpt' => Str::limit(Str::squish($item->content_text ?? $item->snippet ?? ''), 160),
                'attachments' => $this->attachments($item, $versions),
                'quotes' => array_map(
                    fn (array $quote): array => [
                        'name' => $quote['name'] ?? $quote['email'],
                        'date' => $quote['date'],
                        'dateText' => $quote['date_text'],
                        'text' => $quote['text'],
                        'messageId' => $novelty->quotedEmail($quote, $item, $thread)?->id,
                    ],
                    $item->quotes ?? [],
                ),
            ])->values(),
        ]);
    }

    /**
     * Each attachment with what became of it: the document it is, or why it
     * was left out.
     *
     * @param  Collection<int, DocumentVersion>  $versions
     * @return list<array<string, mixed>>
     */
    private function attachments(Email $email, Collection $versions): array
    {
        return array_map(function (array $attachment, int $index) use ($versions): array {
            $version = $versions->get((int) ($attachment['document_version_id'] ?? 0));

            return [
                'index' => $index,
                'filename' => $attachment['filename'],
                'size' => $attachment['size'],
                'decision' => $attachment['decision'] ?? null,
                'reason' => $attachment['reason'] ?? null,
                'canAdd' => ($attachment['decision'] ?? null) === null || in_array($attachment['decision'], ['ignored', 'decorative', 'failed'], true),
                'document' => $version === null ? null : [
                    'id' => $version->document_id,
                    'title' => $version->document->title,
                    'versionNumber' => $version->version_number,
                    'status' => $version->status->value,
                ],
            ];
        }, $email->attachments ?? [], array_keys($email->attachments ?? []));
    }
}
