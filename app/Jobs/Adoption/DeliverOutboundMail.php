<?php

namespace App\Jobs\Adoption;

use App\Models\OutboundMail;
use App\Services\Mail\HowyMailer;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a mail Howy logged and has not sent yet. Carries the id, never the
 * model: SerializesModels would re-fetch it before the tenant context exists.
 * A mail that went already, or may not go, is left as it is (HowyMailer).
 */
class DeliverOutboundMail implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public function __construct(public int $outboundMailId)
    {
        $this->rememberTenant();
    }

    public function handle(HowyMailer $mailer): void
    {
        $mail = OutboundMail::query()->find($this->outboundMailId);

        if ($mail !== null) {
            $mailer->deliver($mail);
        }
    }
}
