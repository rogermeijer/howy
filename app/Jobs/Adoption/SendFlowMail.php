<?php

namespace App\Jobs\Adoption;

use App\Enums\OutboundMailKind;
use App\Facades\Tenancy;
use App\Models\Colleague;
use App\Services\Adoption\FlowMailComposer;
use App\Services\Adoption\Welcomes;
use App\Services\Mail\HowyMailer;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Sends one of Howy's own mails to one colleague, once. Carries the colleague
 * id, never the model: SerializesModels would re-fetch it before the tenant
 * context exists.
 *
 * The colleague's row is locked while it decides, so two jobs for the same
 * mail (two first mails read at once) send it once: the second finds the
 * first, sent or waiting for its mailbox.
 */
class SendFlowMail implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public function __construct(public int $colleagueId, public OutboundMailKind $kind)
    {
        $this->rememberTenant();
    }

    public function handle(HowyMailer $mailer, FlowMailComposer $composer): void
    {
        DB::transaction(function () use ($mailer, $composer): void {
            $colleague = Colleague::query()->with(['homeMailbox', 'user'])->lockForUpdate()->find($this->colleagueId);

            if ($colleague === null || ! Tenancy::account()->isLive()) {
                return;
            }

            $column = $this->kind->colleagueColumn();
            $underway = $colleague->outboundMails()->where('kind', $this->kind)->whereIn('status', Welcomes::underway())->exists();

            if ($underway || ($column !== null && $colleague->getAttribute($column) !== null)) {
                return;
            }

            $mailer->toColleague($colleague, $this->kind, $composer->compose($colleague, $this->kind));
        });
    }
}
