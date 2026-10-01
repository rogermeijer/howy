<?php

namespace App\Services\Gmail;

use App\Enums\MailboxProvider;
use App\Enums\MailboxStatus;
use App\Jobs\StartGmailWatch;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Laravel\Socialite\Two\User as GoogleUser;
use Throwable;

/**
 * Connects and disconnects Gmail mailboxes for the current account.
 */
class MailboxConnector
{
    /**
     * Create the mailbox, or bring an existing one back to life on re-auth.
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

        $mailbox->save();

        StartGmailWatch::dispatch($mailbox->id);

        return $mailbox;
    }

    /**
     * Stop receiving mail and forget the credentials. Stored emails stay: the
     * knowledge base links back to them.
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

        $mailbox->forceFill([
            'status' => MailboxStatus::Disconnected,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'watch_expires_at' => null,
            'import_batch_id' => null,
        ])->save();
    }
}
