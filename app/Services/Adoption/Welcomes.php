<?php

namespace App\Services\Adoption;

use App\Enums\MailboxStatus;
use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Jobs\Adoption\SendFlowMail;
use App\Models\Colleague;
use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who gets Howy's welcome (01), and sending it. A colleague gets it once, and
 * only while the account is live: when it goes live (the admin confirms the
 * list), when an admin adds them, or with their first mail after that.
 */
class Welcomes
{
    /**
     * Whether Howy's own mails may go to this colleague at all: they want
     * them, and the rules of the mailbox they come from do not ignore them.
     */
    public function mayReceive(Colleague $colleague, ?Mailbox $home = null): bool
    {
        $home ??= $colleague->homeMailbox;

        return $colleague->receives_mail
            && $colleague->opted_out_at === null
            && $home !== null
            && $home->status !== MailboxStatus::Disconnected
            && $home->access($colleague->email) !== SenderAccess::Ignore;
    }

    /**
     * Whether they still wait for it: never sent, and none on its way.
     */
    public function awaits(Colleague $colleague): bool
    {
        return $colleague->welcomed_at === null
            && ! $colleague->outboundMails()->where('kind', OutboundMailKind::Welcome)->whereIn('status', self::underway())->exists();
    }

    /**
     * Everyone the welcome would go to now, by name.
     *
     * @return Collection<int, Colleague>
     */
    public function waiting(): Collection
    {
        $mailboxes = Mailbox::query()->with('rules.colleague')->get()->keyBy('id');

        return Colleague::query()
            ->with('homeMailbox:id,email_address')
            ->whereNull('welcomed_at')
            ->whereNull('opted_out_at')
            ->where('receives_mail', true)
            ->whereDoesntHave('outboundMails', fn (Builder $query) => $query
                ->where('kind', OutboundMailKind::Welcome)
                ->whereIn('status', self::underway()))
            ->orderByRaw('lower(coalesce(name, email))')
            ->get()
            ->filter(fn (Colleague $colleague): bool => $this->mayReceive($colleague, $mailboxes->get((int) $colleague->home_mailbox_id)))
            ->values();
    }

    /**
     * Send the welcome to someone who may get it and still waits for it.
     * Anyone else is left alone, so this is safe to call whenever.
     */
    public function send(Colleague $colleague): bool
    {
        if (! Tenancy::account()->isLive() || ! $this->mayReceive($colleague) || ! $this->awaits($colleague)) {
            return false;
        }

        SendFlowMail::dispatch($colleague->id, OutboundMailKind::Welcome);

        return true;
    }

    /**
     * A mail that went, or will once its mailbox is back.
     *
     * @return list<OutboundMailStatus>
     */
    public static function underway(): array
    {
        return [OutboundMailStatus::Pending, OutboundMailStatus::Sent];
    }
}
