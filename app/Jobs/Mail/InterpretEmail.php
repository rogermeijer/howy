<?php

namespace App\Jobs\Mail;

use App\Enums\InterpretationStatus;
use App\Facades\Tenancy;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Services\Mail\Interpretation\EmailInterpreter;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Interprets one mail addressed to a mailbox. Carries the email id, never the
 * model: SerializesModels would re-fetch it before the tenant context exists.
 */
class InterpretEmail implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * @param  bool  $force  interpret again even if it was done before
     */
    public function __construct(public int $emailId, public bool $force = false)
    {
        $this->rememberTenant();
        $this->onQueue((string) config('knowledge.queue'));
    }

    public function handle(EmailInterpreter $interpreter): void
    {
        $email = Email::query()->with('mailbox')->find($this->emailId);

        if ($email === null) {
            return;
        }

        // A redelivered job leaves a finished interpretation alone.
        $status = EmailInterpretation::query()->where('email_id', $email->id)->first()?->status;

        if (! $this->force && $status?->isFinal() === true) {
            return;
        }

        $interpreter->interpret($email);
    }

    /**
     * Runs outside the job middleware, so it sets the tenant itself.
     */
    public function failed(?Throwable $exception): void
    {
        Tenancy::for($this->tenantAccountId, function () use ($exception): void {
            EmailInterpretation::query()->where('email_id', $this->emailId)->update([
                'status' => InterpretationStatus::Failed,
                'error' => Str::limit((string) $exception?->getMessage(), 500),
                'processed_at' => now(),
            ]);
        });
    }
}
