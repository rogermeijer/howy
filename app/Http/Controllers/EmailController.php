<?php

namespace App\Http\Controllers;

use App\Models\Email;
use Illuminate\Database\Eloquent\Collection;
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
    public function show(Email $email): Response
    {
        $thread = Email::query()
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
                        'messageId' => $this->quotedMessage($quote, $item, $thread)?->id,
                    ],
                    $item->quotes ?? [],
                ),
            ])->values(),
        ]);
    }

    /**
     * The earlier message in this conversation that a quote repeats, if any:
     * same sender, and its own text matches the start of the quote.
     *
     * @param  array{name: string|null, email: string|null, date: string|null, date_text: string|null, text: string}  $quote
     * @param  Collection<int, Email>  $thread
     */
    private function quotedMessage(array $quote, Email $quoting, Collection $thread): ?Email
    {
        $needle = Str::limit(Str::squish($quote['text']), 80, '');

        return $thread->first(fn (Email $candidate): bool => ! $candidate->is($quoting)
            && ($quote['email'] === null || $quote['email'] === $candidate->from_email)
            && $needle !== ''
            && str_starts_with(Str::squish($candidate->content_text ?? $candidate->body_text ?? ''), $needle));
    }
}
