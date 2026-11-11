<?php

namespace App\Services\Adoption;

use App\Enums\EmailSource;
use App\Models\Email;

/**
 * What a mail Howy just read sets off in the flow: a colleague's first mail
 * since the account went live brings them the welcome. That mail is their
 * day 0.
 */
class AdoptionTriggers
{
    public function __construct(private readonly Welcomes $welcomes) {}

    public function afterInterpretation(Email $email): void
    {
        $colleague = $email->colleague;

        if ($colleague === null || $email->source === EmailSource::Import) {
            return;
        }

        $this->welcomes->send($colleague);
    }
}
