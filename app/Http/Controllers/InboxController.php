<?php

namespace App\Http\Controllers;

use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Models\Email;
use App\Models\Mailbox;
use App\Services\Mail\Interpretation\InterpretationPresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * The inbox lists conversations, not messages: one row per thread, ordered
     * by its latest message.
     */
    public function index(Request $request, InterpretationPresenter $presenter): Response
    {
        $threads = Email::query()
            ->select(['mailbox_id', 'provider_thread_id'])
            ->selectRaw('MAX(received_at) as last_received_at')
            ->selectRaw('COUNT(*) as messages_count')
            ->selectRaw('BOOL_OR(has_attachments) as any_attachments')
            ->groupBy('mailbox_id', 'provider_thread_id')
            ->orderByDesc('last_received_at')
            ->paginate(50);

        $messages = $this->messagesOf($threads->getCollection());

        $threads->through(function (Email $thread) use ($messages, $presenter): array {
            /** @var Collection<int, Email> $items */
            $items = $messages->get($thread->mailbox_id.'|'.$thread->provider_thread_id, new Collection);
            $first = $items->first();
            $latest = $items->last();
            // A message waiting for review is what the thread is about until
            // someone acts on it; else its latest interpreted message.
            $waiting = $items->last(fn (Email $email): bool => $email->interpretation?->needs_review === true);
            $interpreted = ($waiting ?? $items->last(fn (Email $email): bool => $email->interpretation !== null))?->interpretation;

            return [
                // The row opens the conversation at what needs action, else its latest message.
                'id' => ($waiting ?? $latest)?->id,
                'subject' => $first->subject ?? $latest?->subject,
                'participants' => $items
                    ->map(fn (Email $email): ?string => $email->from_name ?? $email->from_email)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                // The latest message's own words, not the history it quotes.
                'snippet' => Str::limit(Str::squish($latest->content_text ?? $latest->snippet ?? ''), 160),
                'lastReceivedAt' => $latest?->received_at?->toIso8601String(),
                'messagesCount' => (int) $thread->getAttribute('messages_count'),
                'hasAttachments' => (bool) $thread->getAttribute('any_attachments'),
                'interpretation' => $interpreted !== null ? $presenter->brief($interpreted) : null,
            ];
        });

        return Inertia::render('inbox', [
            'threads' => $threads,
            'hasMailbox' => Mailbox::query()->where('status', '!=', MailboxStatus::Disconnected)->exists(),
            'canManageMailboxes' => $request->user()->isAdminOf(Tenancy::account()),
        ]);
    }

    /**
     * The messages of the listed threads, oldest first, keyed "mailbox|thread".
     *
     * @param  Collection<int, Email>  $threads
     * @return BaseCollection<array-key, Collection<int, Email>>
     */
    private function messagesOf(Collection $threads): BaseCollection
    {
        if ($threads->isEmpty()) {
            return new BaseCollection;
        }

        return Email::query()
            ->whereIn('provider_thread_id', $threads->pluck('provider_thread_id')->unique()->values())
            ->whereIn('mailbox_id', $threads->pluck('mailbox_id')->unique()->values())
            ->with('interpretation')
            ->oldest('received_at')
            ->oldest('id')
            ->get(['id', 'mailbox_id', 'provider_thread_id', 'subject', 'from_name', 'from_email', 'snippet', 'content_text', 'received_at'])
            ->groupBy(fn (Email $email): string => $email->mailbox_id.'|'.$email->provider_thread_id)
            ->toBase();
    }
}
