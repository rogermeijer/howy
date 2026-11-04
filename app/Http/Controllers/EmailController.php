<?php

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Email;
use App\Services\Mail\Interpretation\InterpretationPresenter;
use App\Services\Mail\MailNovelty;
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
            ->with('interpretation')
            ->where('mailbox_id', $email->mailbox_id)
            ->where('provider_thread_id', $email->provider_thread_id)
            ->latest('received_at')
            ->latest('id')
            ->get();

        $oldest = $thread->last();
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
                'excerpt' => Str::limit(Str::squish($item->content_text ?? $item->snippet ?? ''), 160),
                'attachments' => array_map(
                    fn (array $a): array => ['filename' => $a['filename'], 'size' => $a['size']],
                    $item->attachments ?? [],
                ),
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
}
