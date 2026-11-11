<?php

namespace App\Http\Controllers\Colleagues;

use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\OutboundMail;
use App\Services\Adoption\FlowSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Every mail Howy sent from the account's mailboxes, or meant to: its own
 * mails to colleagues and its replies, with whether each went, why not, and
 * whether anyone wrote back. For the account's admins, beside the colleagues.
 */
class SentMailController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());
        $kind = OutboundMailKind::tryFrom($request->string('kind')->toString());
        $status = OutboundMailStatus::tryFrom($request->string('status')->toString());
        $from = $this->day($request->string('from')->toString());
        $until = $this->day($request->string('until')->toString());

        $mails = OutboundMail::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('recipient', "%{$search}%")
                ->orWhereHas('colleague', fn (Builder $query) => $query->whereLike('name', "%{$search}%"))))
            ->when($kind !== null, fn (Builder $query) => $query->where('kind', $kind))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($from !== null, fn (Builder $query) => $query->where('created_at', '>=', $from?->copy()->startOfDay()->utc()))
            ->when($until !== null, fn (Builder $query) => $query->where('created_at', '<=', $until?->copy()->endOfDay()->utc()))
            ->with(self::loads())
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (OutboundMail $mail): array => self::present($mail));

        return Inertia::render('colleagues/sent', [
            'mails' => $mails,
            'filters' => [
                'q' => $search,
                'kind' => $kind?->value,
                'status' => $status?->value,
                'from' => $from?->toDateString(),
                'until' => $until?->toDateString(),
            ],
            'kinds' => array_map(fn (OutboundMailKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], OutboundMailKind::cases()),
            'statuses' => array_map(fn (OutboundMailStatus $status): array => ['value' => $status->value, 'label' => $status->label()], OutboundMailStatus::cases()),
        ]);
    }

    /**
     * A logged mail as its recipient got it: the HTML, for the log to show in
     * a frame once it is opened, or the text where no HTML was kept. Nothing
     * in it runs or loads from elsewhere, and only this app may frame it.
     */
    public function mail(OutboundMail $outboundMail): HttpResponse
    {
        $html = filled($outboundMail->html)
            ? $outboundMail->html
            : '<!doctype html><meta charset="utf-8"><body style="margin:0;padding:24px;font:15px/1.6 Helvetica,Arial,sans-serif;color:#0d1b1e;white-space:pre-wrap">'.e((string) $outboundMail->text).'</body>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; frame-ancestors 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Send one of Howy's own mails to a colleague once more, as it was.
     */
    public function resend(OutboundMail $outboundMail, FlowSender $sender): RedirectResponse
    {
        Inertia::flash('toast', $sender->resend($outboundMail)
            ? ['type' => 'success', 'message' => __('The mail is on its way again.')]
            : ['type' => 'error', 'message' => __('This mail cannot be sent again now.')]);

        return back();
    }

    /**
     * What the mail log loads with each mail: who wrote back and what the
     * mail answers, never what it says, which loads once the mail is opened.
     *
     * @return array<int|string, string>
     */
    public static function loads(): array
    {
        return [
            'colleague:id,name,email',
            'mailbox:id,email_address,status',
            'email:id,subject',
            'replies:id,outbound_mail_id,from_name,from_email,snippet,received_at',
        ];
    }

    /**
     * One logged mail as the mail log shows it, here and on a colleague's
     * page.
     *
     * @return array<string, mixed>
     */
    public static function present(OutboundMail $mail): array
    {
        return [
            'id' => $mail->id,
            'recipient' => $mail->recipient,
            'cc' => $mail->cc ?? [],
            'colleague' => $mail->colleague !== null ? ['id' => $mail->colleague->id, 'name' => $mail->colleague->displayName()] : null,
            'mailbox' => $mail->mailbox?->email_address,
            'kind' => $mail->kind->value,
            'kindLabel' => $mail->kind->label(),
            'status' => $mail->status->value,
            'statusLabel' => $mail->status->label(),
            'reason' => $mail->skip_reason?->label() ?? $mail->error ?? self::waitingFor($mail),
            'subject' => $mail->subject,
            // Something to show once it is opened: the HTML, or else the text.
            'hasContent' => filled($mail->html) || filled($mail->text),
            'answers' => $mail->email !== null ? ['id' => $mail->email->id, 'subject' => $mail->email->subject] : null,
            'replies' => $mail->replies
                ->sortBy('received_at')
                ->map(fn (Email $email): array => [
                    'id' => $email->id,
                    'fromName' => $email->from_name,
                    'fromEmail' => $email->from_email,
                    'snippet' => Str::limit(Str::squish(html_entity_decode((string) $email->snippet, ENT_QUOTES | ENT_HTML5)), 120),
                    'receivedAt' => $email->received_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'canResend' => FlowSender::resendable($mail),
            'createdAt' => $mail->created_at?->toIso8601String(),
            'sentAt' => $mail->sent_at?->toIso8601String(),
        ];
    }

    /**
     * What a mail not sent yet waits for, if anything: about to go, it waits
     * for nothing.
     */
    private static function waitingFor(OutboundMail $mail): ?string
    {
        if ($mail->status !== OutboundMailStatus::Pending) {
            return null;
        }

        return match (true) {
            $mail->mailbox !== null && ! $mail->mailbox->isActive() => (string) __('The mailbox needs reconnecting.'),
            $mail->kind->isFlow() && ! Tenancy::account()->isLive() => (string) __('Howy is paused.'),
            default => null,
        };
    }

    /**
     * A day as the admin picked it, in the account's timezone.
     */
    private function day(string $value): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, Tenancy::account()->timezone)?->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
