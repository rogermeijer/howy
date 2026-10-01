# Gmail mailboxes

An account admin connects a shared Gmail mailbox (support@, kennis@…) under **Settings › Mailboxes**.
From then on, every new INBOX message is stored as an `Email`. Existing mail can be imported by
searching the mailbox and picking messages.

## How mail arrives

1. **Connect.** OAuth through Socialite requests the scopes `openid email gmail.readonly gmail.send`,
   offline, with `prompt=consent`. `MailboxConnector` stores the mailbox with encrypted tokens and queues
   `StartGmailWatch`.
2. **Watch.** `StartGmailWatch` calls `users.watch` on the Pub/Sub topic and records `history_id` and
   `watch_expires_at`. A watch lasts about 7 days; `mailboxes:renew-watches` runs daily and renews any
   watch within 2 days of expiring.
3. **Push.** Gmail publishes `{emailAddress, historyId}` to the topic, and Pub/Sub POSTs it to
   `POST /webhooks/gmail`. The controller:
    - verifies the Google-signed OIDC token;
    - finds the mailbox or mailboxes with that address (see the tenancy exception in
      `docs/multi-tenancy.md`);
    - queues `SyncGmailMailbox` inside each owning account;
    - always answers 204, so Pub/Sub does not retry.
4. **Sync.** `SyncGmailMailbox` pages through `users.history.list` from the stored history id, fetches each
   new message with `format=full` and stores it through `StoreGmailMessage`. That upserts on
   `(account, mailbox, message id)`, so redelivery is harmless.
    - If the history id has aged out (a 404 from Gmail), it catches up on the last day instead and
      restarts from the current id.
5. **Poll.** `mailboxes:poll` runs every 5 minutes as a safety net. Locally, where Google cannot reach
   the app, it is the only way new mail arrives.

A refresh token that Google refuses flips the mailbox to `needs_reauth`. The settings page then offers
**Reconnect**. Disconnecting stops the watch, revokes the grant and wipes the tokens. **Stored emails are
kept**, because knowledge-base files link back to them.

## Import

The import dialog searches Gmail live (`messages.list` plus metadata per message). Nothing is stored until
someone imports. The import request:

- optionally expands the selection to whole threads;
- skips messages that are already stored;
- queues a `Bus::batch` of `ImportGmailMessages` chunks.

The batch id goes on `mailboxes.import_batch_id`, and the progress bar reads it from `job_batches`.
There is no imports table.

## Sending

`gmail.send` is requested now, so mailboxes will not have to reconnect when sending ships. Each mailbox
has `send_policy` (`off` | `domain` | `allowlist`, default `off`) and `send_allowlist`. **Nothing sends
yet.**

## One-time Google Cloud setup

1. Create or pick a Google Cloud project and enable the **Gmail API** and **Cloud Pub/Sub API**.
2. **OAuth consent screen:**
    - Add the scopes above.
    - While in testing, add up to 100 test users.
    - `gmail.readonly` is a _restricted_ scope. Going to production needs Google's verification plus a
      yearly CASA security assessment. `gmail.send` is only _sensitive_ and adds nothing on top.
3. **Credentials → OAuth client ID** (web application):
    - Authorised redirect URI: `${APP_URL}/settings/mailboxes/gmail/callback`.
    - Put the id and secret in `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`.
4. **Pub/Sub:**
    - Create a topic, for example `projects/<project>/topics/cc-gmail`, and set it as
      `GOOGLE_PUBSUB_TOPIC`.
    - Grant `gmail-api-push@system.gserviceaccount.com` the **Pub/Sub Publisher** role on the topic.
5. **Push subscription** on that topic:
    - Endpoint: `${APP_URL}/webhooks/gmail`.
    - Enable authentication with a service account of your own. Set `GOOGLE_PUBSUB_SERVICE_ACCOUNT` to its
      email and `GOOGLE_PUBSUB_AUDIENCE` to the endpoint URL (the default audience).
6. Run a queue worker and the scheduler (`php artisan schedule:work` locally).

Local development without a public URL works without steps 4 and 5. Leave `GOOGLE_PUBSUB_TOPIC` empty and
new mail comes in through `mailboxes:poll`. To test real pushes, set `NGROK_DOMAIN` to your reserved
ngrok hostname and run `composer dev` — ngrok starts alongside `php artisan serve` automatically.

You can keep browsing on Valet (`APP_URL=https://cc.test`) and use the tunnel URL when you need a public
endpoint. Vite keeps serving from `https://cc.test:5173`; with `NGROK_DOMAIN` set, that dev server also
allows the ngrok origin so the UI works on both hosts. Point the Pub/Sub subscription (and
`GOOGLE_PUBSUB_AUDIENCE`) at `https://<ngrok-host>/webhooks/gmail`. For Gmail OAuth over the tunnel, add
that host’s callback URL in Google Cloud or temporarily set `GOOGLE_REDIRECT_URI` to match.
