<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\EmailSource;
use App\Enums\InterpretationMode;
use App\Enums\SenderAccess;
use App\Models\Email;
use App\Models\Mailbox;

/**
 * Whether Howy should interpret a mail, and in which role: one that arrived
 * live, written by a person rather than a machine, not by the mailbox (its
 * own replies must never loop), and by someone the mailbox's rules let Howy
 * do something with. Written to the mailbox, Howy may answer; only copied on
 * it, Howy only listens, so a sender it may only answer gets nothing there.
 */
class EmailEligibility
{
    public function mode(Email $email, Mailbox $mailbox): ?InterpretationMode
    {
        if (! $this->considered($email, $mailbox) || $this->refusal($email, $mailbox) !== null) {
            return null;
        }

        return $this->role($email, $mailbox);
    }

    /**
     * What the mailbox's rules let Howy do with the sender's mail.
     */
    public function access(Email $email, Mailbox $mailbox): SenderAccess
    {
        return $mailbox->access((string) $email->from_email);
    }

    /**
     * Why Howy leaves a mail it would read alone: stored, and marked as
     * ignored so it is clear why nothing followed. Null when it is read, or
     * when it is not Howy's to read at all (imported, automated, its own).
     */
    public function refusal(Email $email, Mailbox $mailbox): ?string
    {
        if (! $this->considered($email, $mailbox)) {
            return null;
        }

        $access = $this->access($email, $mailbox);

        return match (true) {
            $access === SenderAccess::Ignore => (string) __('The sender is outside the rules of this mailbox.'),
            ! $access->learns() && $this->role($email, $mailbox) === InterpretationMode::Copied => (string) __('Howy only answers this sender, and was only copied on this mail.'),
            default => null,
        };
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
     * Written to the mailbox and to no one else: the sender expects Howy to act
     * on it, so a request to it counts as a question.
     */
    public function writtenToAlone(Email $email, ?Mailbox $mailbox): bool
    {
        if ($mailbox === null) {
            return false;
        }

        $to = collect($email->to ?? [])->map(fn (array $recipient): string => strtolower($recipient['email']))->unique()->values()->all();

        return $to === [strtolower($mailbox->email_address)];
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
