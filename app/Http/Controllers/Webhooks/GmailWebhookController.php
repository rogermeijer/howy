<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\EmailSource;
use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Jobs\SyncGmailMailbox;
use App\Models\Mailbox;
use App\Services\Google\PubSubTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Gmail's "your mailbox changed" pushes via Cloud Pub/Sub.
 *
 * TENANCY EXCEPTION — the one request path allowed to call withoutTenancy().
 * A push carries no session, only an email address, so finding which account
 * owns that address has to look across accounts. The bypass covers exactly one
 * read of ids; everything after it runs inside Tenancy::for() and re-loads the
 * mailbox through the normal scope. See CLAUDE.md rule 5 and
 * WithoutTenancyGuardTest, which pins the exception to this file.
 */
class GmailWebhookController extends Controller
{
    public function __invoke(Request $request, PubSubTokenVerifier $verifier): Response
    {
        abort_unless($verifier->verify($request->bearerToken()), 401);

        /** @var array{emailAddress?: string, historyId?: string|int}|null $data */
        $data = json_decode((string) base64_decode((string) $request->input('message.data', ''), true), true);

        $address = is_array($data) ? mb_strtolower((string) ($data['emailAddress'] ?? '')) : '';

        // Always acknowledge. Anything but 2xx makes Pub/Sub redeliver, which for
        // an address we do not (or no longer) know would retry for a week.
        if ($address === '') {
            return response()->noContent();
        }

        /** @var list<array{id: int, account_id: int}> $targets */
        $targets = Tenancy::withoutTenancy(fn () => Mailbox::query()
            ->where('email_address', $address)
            ->where('status', MailboxStatus::Active)
            ->get(['id', 'account_id'])
            ->map(fn (Mailbox $mailbox): array => ['id' => $mailbox->id, 'account_id' => $mailbox->account_id])
            ->all());

        foreach ($targets as $target) {
            Tenancy::for($target['account_id'], function () use ($target): void {
                $mailbox = Mailbox::query()->findOrFail($target['id']);

                SyncGmailMailbox::dispatch($mailbox->id, EmailSource::Push);
            });
        }

        return response()->noContent();
    }
}
