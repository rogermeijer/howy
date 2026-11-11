<?php

namespace App\Services\Gmail;

use App\Enums\MailboxProvider;
use App\Enums\MailboxStatus;
use App\Enums\OutboundMailStatus;
use App\Enums\OutboundSkipReason;
use App\Enums\SenderAccess;
use App\Exceptions\MailboxConnectedElsewhereException;
use App\Jobs\StartGmailWatch;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use App\Services\Colleagues\Colleagues;
use App\Services\Mailboxes\MailboxRuleEditor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Two\User as GoogleUser;
use Throwable;

/**
 * Connects and disconnects Gmail mailboxes for the current account.
 */
class MailboxConnector
{
    /** The partial index that keeps an address active in one account at most. */
    private const string ACTIVE_ADDRESS_INDEX = 'mailboxes_one_active_per_address';

    public function __construct(
        private readonly MailboxRuleEditor $rules,
        private readonly Colleagues $colleagues,
    ) {}

    /**
     * Create the mailbox, or bring an existing one back to life on re-auth.
     *
     * @throws MailboxConnectedElsewhereException when the address is active in another account.
     */
    public function connect(GoogleUser $google, User $by): Mailbox
    {
        $mailbox = Mailbox::query()->firstOrNew([
            'provider' => MailboxProvider::Gmail,
            'email_address' => mb_strtolower((string) $google->getEmail()),
        ]);

        $mailbox->fill([
            'connected_by_user_id' => $by->id,
            'provider_user_id' => (string) $google->getId(),
            'access_token' => $google->token,
            // Google only returns a refresh token on the first consent; keep the
            // stored one if this is a re-auth that did not include a new one.
            'refresh_token' => filled($google->refreshToken) ? $google->refreshToken : $mailbox->refresh_token,
            'token_expires_at' => now()->addSeconds($google->expiresIn ?: 3600),
            'status' => MailboxStatus::Active,
            'last_error' => null,
        ]);

        // The database decides, so two accounts connecting at once cannot both
        // win. In its own transaction, so a refused write cannot poison an outer
        // one. Nothing is revoked on refusal: the grant Google just handed out is
        // the one the other account is using.
        try {
            $mailbox->saveOrFail();
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), self::ACTIVE_ADDRESS_INDEX)) {
                throw MailboxConnectedElsewhereException::for($mailbox->email_address);
            }

            throw $e;
        }

        // A new mailbox learns from and answers its own domain, whose people
        // become colleagues. On gmail.com "the same domain" is everyone: a
        // mailbox there reads only the senders listed, starting with whoever
        // connected it. Connected again, a mailbox keeps the rules it has.
        if ($mailbox->wasRecentlyCreated) {
            $mailbox->isOnPublicDomain()
                ? $this->rules->setFor($mailbox, $this->colleagues->findOrCreate($by->email, $mailbox, $by->name), SenderAccess::LearnAndAnswer)
                : $this->rules->setForDomain($mailbox, $mailbox->domain(), SenderAccess::LearnAndAnswer, createsColleagues: true);
        }

        StartGmailWatch::dispatch($mailbox->id);

        return $mailbox;
    }

    /**
     * Stop receiving mail and forget the credentials. Stored emails stay: the
     * knowledge base links back to them. So do the rules, for when it is
     * connected again. Nothing of Howy's own goes out from it any more: whoever
     * had it as their home mailbox has none, until an admin chooses one or
     * they mail another, and what waited on it is skipped.
     */
    public function disconnect(Mailbox $mailbox): void
    {
        if ($mailbox->isActive()) {
            try {
                $client = GmailClient::for($mailbox);
                $client->stop();
                $client->revoke();
            } catch (Throwable) {
                // Already revoked or unreachable. Disconnecting must still work.
            }
        }

        if ($mailbox->import_batch_id !== null) {
            Bus::findBatch($mailbox->import_batch_id)?->cancel();
        }

        DB::transaction(function () use ($mailbox): void {
            $mailbox->forceFill([
                'status' => MailboxStatus::Disconnected,
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'watch_expires_at' => null,
                'import_batch_id' => null,
            ])->save();

            Colleague::query()->where('home_mailbox_id', $mailbox->id)->update(['home_mailbox_id' => null]);

            OutboundMail::query()
                ->whereBelongsTo($mailbox)
                ->where('status', OutboundMailStatus::Pending)
                ->get()
                ->each(fn (OutboundMail $mail) => $mail->forceFill([
                    'status' => OutboundMailStatus::Skipped,
                    'skip_reason' => OutboundSkipReason::NoMailbox,
                ])->save());
        });
    }
}
