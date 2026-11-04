<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\EmailSource;
use App\Enums\InterpretationMode;
use App\Models\Email;
use App\Models\Mailbox;

/**
 * Whether Howy should interpret a mail, and in which role: one that arrived
 * live, written by a person rather than a machine, not by the mailbox (its
 * own replies must never loop), and by someone the mailbox's rules let in.
 * Written to the mailbox, Howy answers; only copied on it, Howy listens.
 */
class EmailEligibility
{
    public function mode(Email $email, Mailbox $mailbox): ?InterpretationMode
    {
        if (! $this->considered($email, $mailbox) || ! $mailbox->accepts((string) $email->from_email)) {
            return null;
        }

        return $this->role($email, $mailbox);
    }

    /**
     * Mail Howy would read, but from a sender the mailbox's rules keep out:
     * stored, and marked as ignored so it is clear why nothing followed.
     */
    public function refused(Email $email, Mailbox $mailbox): bool
    {
        return $this->considered($email, $mailbox) && ! $mailbox->accepts((string) $email->from_email);
    }

    /**
     * The role Howy takes in a mail, whether or not it is interpreted on its
     * own: answering when the mailbox is in To, listening otherwise.
     */
    public function role(Email $email, ?Mailbox $mailbox): InterpretationMode
    {
        if ($mailbox === null) {
            return InterpretationMode::Addressed;
        }

        $address = strtolower($mailbox->email_address);
        $addressed = collect($email->to ?? [])->contains(fn (array $recipient): bool => strtolower($recipient['email']) === $address);

        return $addressed ? InterpretationMode::Addressed : InterpretationMode::Copied;
    }

    /**
     * Live mail from a person other than the mailbox itself.
     */
    private function considered(Email $email, Mailbox $mailbox): bool
    {
        return $email->source !== EmailSource::Import
            && ! $this->automated($email)
            && strtolower((string) $email->from_email) !== strtolower($mailbox->email_address);
    }

    /**
     * Auto-replies, mailing lists and bulk mail announce themselves (RFC 3834).
     */
    private function automated(Email $email): bool
    {
        $autoSubmitted = strtolower(trim((string) $email->header('Auto-Submitted')));
        $precedence = strtolower(trim((string) $email->header('Precedence')));

        return ($autoSubmitted !== '' && $autoSubmitted !== 'no')
            || in_array($precedence, ['bulk', 'junk', 'list', 'auto_reply'], true)
            || $email->header('List-Id') !== null;
    }
}
