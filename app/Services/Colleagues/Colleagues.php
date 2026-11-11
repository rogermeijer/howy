<?php

namespace App\Services\Colleagues;

use App\Enums\EmailSource;
use App\Facades\Tenancy;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Mail\Interpretation\EmailEligibility;
use Illuminate\Support\Carbon;

/**
 * Who the colleagues are. One is made when a rule names them, when a domain
 * rule that creates colleagues lets their mail in, or by hand; after that
 * every mail they send is linked to them.
 */
class Colleagues
{
    public function __construct(private readonly EmailEligibility $eligibility) {}

    /**
     * The colleague with this address, made when there is none: their home
     * mailbox is this one, and Howy's own mails go to them when they are at
     * the company's domain or a member of the account, unless $receivesMail
     * says otherwise.
     */
    public function findOrCreate(string $email, Mailbox $mailbox, ?string $name = null, ?bool $receivesMail = null): Colleague
    {
        $email = strtolower(trim($email));
        $colleague = Colleague::query()->where('email', $email)->first();

        if ($colleague !== null) {
            return $colleague;
        }

        $member = $this->memberWith($email);

        // createOrFirst: someone made at the same moment (a push and a poll
        // of the same mail) is found rather than refused.
        return Colleague::query()->createOrFirst(['email' => $email], [
            'name' => filled($name) ? $name : null,
            'home_mailbox_id' => $mailbox->id,
            'user_id' => $member,
            'receives_mail' => $receivesMail ?? ($member !== null || $this->atTheCompany($email, $mailbox)),
        ]);
    }

    /**
     * Keep up with a mail that came in: its sender becomes a colleague when
     * the mailbox's rules say so, and a colleague's mail is linked to them.
     * Only mail a person wrote counts, and an import makes no one: it only
     * links mail from people who are colleagues already.
     */
    public function record(Email $email, Mailbox $mailbox): ?Colleague
    {
        $address = strtolower(trim((string) $email->from_email));

        if ($address === '' || ! $this->eligibility->fromPerson($email, $mailbox)) {
            return null;
        }

        $colleague = Colleague::query()->where('email', $address)->first();

        if ($colleague === null) {
            if ($email->source === EmailSource::Import || ! $mailbox->createsColleague($address)) {
                return null;
            }

            // A rule that makes colleagues is one for the company's people:
            // the own domain, or one an admin chose, a subsidiary say.
            $colleague = $this->findOrCreate($address, $mailbox, $email->from_name, receivesMail: true);
        }

        $at = $email->received_at ?? $email->sent_at ?? Carbon::now();

        $colleague->name ??= filled($email->from_name) ? $email->from_name : null;
        $colleague->home_mailbox_id ??= $mailbox->id;
        $colleague->user_id ??= $this->memberWith($address);

        if ($colleague->first_seen_at === null || $at->isBefore($colleague->first_seen_at)) {
            $colleague->first_seen_at = $at;
        }

        if ($colleague->last_mail_at === null || $at->isAfter($colleague->last_mail_at)) {
            $colleague->last_mail_at = $at;
        }

        $colleague->save();

        if ($email->colleague_id !== $colleague->id) {
            $email->colleague()->associate($colleague)->save();
        }

        return $colleague;
    }

    /**
     * At the mailbox's own domain (not a public one), or the account's.
     */
    private function atTheCompany(string $email, Mailbox $mailbox): bool
    {
        $domain = Mailbox::domainOf($email);

        return ($domain === $mailbox->domain() && ! $mailbox->isOnPublicDomain())
            || $domain === strtolower((string) Tenancy::account()->domain);
    }

    /**
     * The member of this account with this address.
     */
    private function memberWith(string $email): ?int
    {
        /** @var int|null */
        return User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->whereHas('accounts', fn ($query) => $query->whereKey(Tenancy::id()))
            ->value('id');
    }
}
