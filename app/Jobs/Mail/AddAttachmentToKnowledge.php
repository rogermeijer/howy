<?php

namespace App\Jobs\Mail;

use App\Models\Email;
use App\Services\Mail\MailAttachments;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * An administrator adds a mail's attachment to the knowledge base by hand.
 * Downloading it takes a moment, so it happens on the queue. Carries the email
 * id, never the model: SerializesModels would re-fetch it before the tenant
 * context exists.
 */
class AddAttachmentToKnowledge implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public int $emailId, public int $index)
    {
        $this->rememberTenant();
        $this->onQueue((string) config('knowledge.queue'));
    }

    public function handle(MailAttachments $attachments): void
    {
        $email = Email::query()->with(['mailbox', 'interpretation'])->find($this->emailId);

        if ($email === null) {
            return;
        }

        $attachments->addByHand($email, $this->index);
    }
}
