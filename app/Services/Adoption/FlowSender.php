<?php

namespace App\Services\Adoption;

use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Jobs\Adoption\DeliverOutboundMail;
use App\Models\Colleague;
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
     * Whether they had this mail, or it is on its way.
     */
    public function underway(Colleague $colleague, OutboundMailKind $kind): bool
    {
        $column = $kind->colleagueColumn();

        return ($column !== null && $colleague->getAttribute($column) !== null)
            || $colleague->outboundMails()->where('kind', $kind)->whereIn('status', OutboundMailStatus::underway())->exists();
    }
}
