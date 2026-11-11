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
   `(account, mailbox, message id)`, so redelivery is harmless, and keeps up with the sender as a colleague
   (`Colleagues::record()`: made when the rule that lets them in creates colleagues, never from an import;
   `emails.colleague_id`, `last_mail_at`).
    - If the history id has aged out (a 404 from Gmail), it catches up on the last day instead and
      restarts from the current id.
5. **Poll.** `mailboxes:poll` runs every 5 minutes as a safety net. Locally, where Google cannot reach
   the app, it is the only way new mail arrives.

A refresh token that Google refuses flips the mailbox to `needs_reauth`. The settings page then offers
**Reconnect**. Disconnecting stops the watch, revokes the grant and wipes the tokens. **Stored emails are
kept**, because knowledge-base files link back to them, and so are the mailbox's rules, for when it is
connected again. A disconnected mailbox is no longer offered anywhere (`Mailbox::connected()`). What leaned
on it lets go: colleagues who had it as their home mailbox have none, and Howy's mails waiting on it are
skipped (see "The flow" and "Colleagues" in `docs/adoption-mails.md`).

## Import

The import dialog searches Gmail live (`messages.list` plus metadata per message). Nothing is stored until
someone imports. The import request:

- optionally expands the selection to whole threads;
- skips messages that are already stored;
- queues a `Bus::batch` of `ImportGmailMessages` chunks.

The batch id goes on `mailboxes.import_batch_id`, and the progress bar reads it from `job_batches`.
There is no imports table.

## Sending

Howy replies in the thread with `gmail.send`, and only to senders the mailbox's rules let it answer. Each
mailbox has rules in `mailbox_rules`, per colleague or per domain, its own domain among them (learn, answer, both
or ignore); everyone else is ignored. See "Sender rules" in [`knowledge-base.md`](knowledge-base.md).

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
4. **Pub/Sub topic:**
    - Create a topic, for example `projects/<project>/topics/cc-gmail`, and set it as
      `GOOGLE_PUBSUB_TOPIC`.
    - Grant `gmail-api-push@system.gserviceaccount.com` the **Pub/Sub Publisher** role on the topic.
      That is Google's Gmail push account, not the one you create in the next step.
5. **Service account for the push** (IAM & Admin → Service accounts → Create service account), in the
   same project:
    - Name it something like `Gmail Pub/Sub push`; the id becomes the email, for example
      `gmail-pubsub-push@<project>.iam.gserviceaccount.com`.
    - Grant it **no project roles**, and do **not** create a key. The app never holds this account's
      credentials; Pub/Sub only mints an OIDC token as this identity.
    - Set `GOOGLE_PUBSUB_SERVICE_ACCOUNT` to that email.
6. **Push subscription** on the topic (Pub/Sub → Subscriptions → Create subscription):
    - Delivery type: **Push**. Endpoint: `${APP_URL}/webhooks/gmail`.
    - Tick **Enable authentication**, pick the service account from step 5, and leave the audience at
      the default, which is the endpoint URL itself.
    - Pub/Sub signs that token as a Google-managed agent,
      `service-<project-number>@gcp-sa-pubsub.iam.gserviceaccount.com`. The project number is on the
      dashboard under **Project info**; the agent itself only shows on **IAM & Admin → IAM** after you
      tick **Include Google-provided role grants**. It needs permission to mint tokens
      (`iam.serviceAccounts.getOpenIdToken`). On a project created after 8 April 2021 that already comes
      with the **Pub/Sub Service Agent** role, so you add nothing. The create-subscription screen says
      so and offers **Grant** for **Service Account Token Creator** when the role is missing — only
      then do you grant it.
    - Set `GOOGLE_PUBSUB_AUDIENCE` to that same URL. The webhook rejects the push unless the token is
      signed by Google, its `aud` equals this value exactly, and its `email` is the service account
      with `email_verified`.
7. Run a queue worker and the scheduler. Locally, `composer dev` starts Horizon and `schedule:work`. On the server those are the Horizon daemon and the every-minute `schedule:run` cron (`docs/deployment.md`).

Local development without a public URL works without steps 4 to 6. Leave `GOOGLE_PUBSUB_TOPIC` empty and
new mail comes in through `mailboxes:poll`. To test real pushes, set `NGROK_DOMAIN` to your reserved
ngrok hostname and run `composer dev` — ngrok starts alongside `php artisan serve` automatically.

You can keep browsing on Valet (`APP_URL=https://cc.test`) and use the tunnel URL when you need a public
endpoint. Vite keeps serving from `https://cc.test:5173`; with `NGROK_DOMAIN` set, that dev server also
allows the ngrok origin so the UI works on both hosts. Point the Pub/Sub subscription (and
`GOOGLE_PUBSUB_AUDIENCE`) at `https://<ngrok-host>/webhooks/gmail`.

Google does not accept a `.test` host as redirect URI, so Gmail OAuth also goes through the tunnel. Register
`https://<ngrok-host>/settings/mailboxes/gmail/callback` in Google Cloud and set `GOOGLE_REDIRECT_URI` to it.
Keep starting the connect flow on `APP_URL`, where you are logged in: when the callback comes back over the
tunnel, `ForwardTunnelledOAuthCallback` sends it on to the same path on `APP_URL` with its query intact, so
the session, the OAuth state and the account checks run there as usual. Only that GET path on the
`NGROK_DOMAIN` host is forwarded; the webhook and everything else over the tunnel are left alone.
