<?php

namespace App\Http\Controllers\Colleagues;

use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\OutboundMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
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
            ->with(['colleague:id,name,email', 'mailbox:id,email_address', 'replies:id,outbound_mail_id'])
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
            'reason' => $mail->skip_reason?->label() ?? $mail->error,
            'subject' => $mail->subject,
            'text' => $mail->text,
            'emailId' => $mail->email_id,
            'replyIds' => $mail->replies->map(fn (Email $email): int => $email->id)->values()->all(),
            'createdAt' => $mail->created_at?->toIso8601String(),
            'sentAt' => $mail->sent_at?->toIso8601String(),
        ];
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
