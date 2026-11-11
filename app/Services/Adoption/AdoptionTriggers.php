<?php

namespace App\Services\Adoption;

use App\Enums\EmailSource;
use App\Enums\InterpretationOutcome;
use App\Enums\OutboundMailKind;
use App\Facades\Tenancy;
use App\Models\Colleague;
use App\Models\Email;

/**
 * What a mail Howy just read sets off in the flow:
 *
 * - a colleague's first mail since the account went live brings them the
 *   welcome. That mail is their day 0, and nothing else follows from it;
 * - the first mail after the welcome that Howy files something from brings
 *   them its thanks (04), to them alone, as a message of its own: never in
 *   the thread, where customers and suppliers often are.
 */
class AdoptionTriggers
{
    public function __construct(
        private readonly Welcomes $welcomes,
        private readonly FlowSender $sender,
    ) {}

    public function afterInterpretation(Email $email): void
    {
        // As the interpretation left them, not as they were loaded before it.
        $email->load(['interpretation', 'colleague']);
        $colleague = $email->colleague;

        if ($colleague === null || $email->source === EmailSource::Import) {
            return;
        }

        if ($this->welcomes->send($colleague)) {
            return;
        }

        if ($this->isFirstContribution($email, $colleague)) {
            $this->sender->send($colleague, OutboundMailKind::FirstSave, $email);
        }
    }

    /**
     * Something new filed from a mail they sent after the welcome, while the
     * account is live and they get Howy mails. FlowSender sends it once.
     */
    private function isFirstContribution(Email $email, Colleague $colleague): bool
    {
        $interpretation = $email->interpretation;
        $sentAt = $email->received_at ?? $email->created_at;

        return Tenancy::account()->isLive()
            && $interpretation?->outcome === InterpretationOutcome::Added
            && collect($interpretation->statements ?? [])->contains(fn (array $statement): bool => $statement['verdict'] === 'new')
            && $colleague->welcomed_at !== null
            && $sentAt !== null
            && $colleague->welcomed_at->lt($sentAt)
            && $this->welcomes->mayReceive($colleague);
    }
}
