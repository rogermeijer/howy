<?php

namespace App\Services\Adoption;

use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Jobs\Adoption\DeliverOutboundMail;
use App\Models\Colleague;
use App\Models\OutboundMail;
use App\Services\Mail\HowyMailer;
use Illuminate\Support\Facades\DB;

/**
 * Sends one of Howy's own mails to one colleague, once. It is written and
 * logged at once, as a mail not sent yet, and a job delivers it: what is
 * underway shows straight away, on the colleague's page and in the mail log.
 *
 * The colleague's row is locked while it decides, so asking twice (two first
 * mails read at once, a button clicked twice) logs the mail once.
 */
class FlowSender
{
    public function __construct(
        private readonly HowyMailer $mailer,
        private readonly FlowMailComposer $composer,
    ) {}

    public function send(Colleague $colleague, OutboundMailKind $kind): bool
    {
        return DB::transaction(function () use ($colleague, $kind): bool {
            $colleague = Colleague::query()->with(['homeMailbox', 'user'])->lockForUpdate()->find($colleague->id);

            if ($colleague === null || $this->underway($colleague, $kind)) {
                return false;
            }

            $mail = $this->mailer->logForColleague($colleague, $kind, $this->composer->compose($colleague, $kind));

            DeliverOutboundMail::dispatch($mail->id)->afterCommit();

            return true;
        });
    }

    /**
     * The same mail to the same colleague once more, as it was written: for
     * one that did not arrive. Logged at once and delivered by a job, like
     * any other; one resend on its way at a time.
     */
    public function resend(OutboundMail $mail): bool
    {
        if (! self::resendable($mail)) {
            return false;
        }

        return DB::transaction(function () use ($mail): bool {
            $colleague = Colleague::query()->lockForUpdate()->find($mail->colleague_id);

            if ($colleague === null || $colleague->outboundMails()->where('kind', $mail->kind)->where('status', OutboundMailStatus::Pending)->exists()) {
                return false;
            }

            DeliverOutboundMail::dispatch($this->mailer->logAgain($mail)->id)->afterCommit();

            return true;
        });
    }

    /**
     * One of Howy's own mails to a colleague that went, or failed: a reply in
     * someone's thread is not sent twice.
     */
    public static function resendable(OutboundMail $mail): bool
    {
        return $mail->kind->isFlow()
            && $mail->colleague_id !== null
            && in_array($mail->status, [OutboundMailStatus::Sent, OutboundMailStatus::Failed], true);
    }

    /**
     * Whether they had this mail, or it is on its way.
     */
    public function underway(Colleague $colleague, OutboundMailKind $kind): bool
    {
        $column = $kind->colleagueColumn();

        return ($column !== null && $colleague->getAttribute($column) !== null)
            || $colleague->outboundMails()->where('kind', $kind)->whereIn('status', OutboundMailStatus::underway())->exists();
    }
}
