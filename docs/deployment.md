# Deployment (Laravel Forge / Ubuntu VPS)

What a fresh Forge server needs before Howy deploys and runs. Forge provisions PHP, Nginx, Node, Redis and
PostgreSQL; the items below are what Howy adds on top. Commands run over SSH as the `forge` user unless noted.

| Needed for                              | What                                                 | Forge installs it?        |
| --------------------------------------- | ---------------------------------------------------- | ------------------------- |
| Embeddings, hybrid search               | PostgreSQL extension `vector` (pgvector)             | No                        |
| Topic folder tree                       | PostgreSQL extension `ltree` (contrib)               | Package yes, extension no |
| PDF extraction                          | `pdftohtml`, `pdfinfo`, `pdftoppm` (`poppler-utils`) | No                        |
| Scanned-PDF fallback                    | `qpdf`                                               | No                        |
| Office, text and CSV extraction         | `pandoc` (with the pptx and xlsx readers)            | No                        |
| Queues (`default`, `knowledge`)         | Redis + Horizon daemon                               | Redis yes, daemon no      |
| Gmail watch renewal, polling, Batch API | Scheduler (`schedule:run` every minute)              | No (toggle)               |
| Frontend build                          | Node 24 (major from `.node-version`)                 | Check version             |

## 1. PostgreSQL: pgvector and ltree

`extension "vector" is not available` means the pgvector package for the server's PostgreSQL major version
is not installed. Find that version first; the package name has to match it.

```bash
pg_lsclusters          # e.g. "18  main  5432 online"
```

Howy targets PostgreSQL 18 (CI runs `pgvector/pgvector:pg18`). Install the matching pgvector build from the
PGDG repository, which Forge already configures:

```bash
sudo apt update
sudo apt install postgresql-18-pgvector     # use the major version from pg_lsclusters
```

If apt cannot find the package, the PGDG repository is missing:

```bash
sudo apt install -y postgresql-common
sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh
sudo apt install postgresql-18-pgvector
```

`CREATE EXTENSION` needs a superuser, so create both extensions once as `postgres`, in the app's database
(Forge's default is `forge`). The migration `enable_knowledge_extensions` is then a no-op.

```bash
sudo -u postgres psql -d forge -c 'CREATE EXTENSION IF NOT EXISTS vector; CREATE EXTENSION IF NOT EXISTS ltree;'
sudo -u postgres psql -d forge -c '\dx'      # should list vector and ltree
```

Repeat for every database Howy uses on that server (e.g. a staging database).

## 2. Document extraction tools

```bash
sudo apt install poppler-utils pandoc qpdf
which pdftohtml pdfinfo pdftoppm qpdf pandoc  # all under /usr/bin
```

On Ubuntu they land in `/usr/bin`, which php-fpm and the Horizon daemon see, so the `KNOWLEDGE_PDFTOHTML`
etc. overrides in `.env` are not needed. Set them only if `which` shows another path.

pandoc reads every office and text format the knowledge base accepts (docx, pptx, xlsx, odt, rtf, csv, tsv,
html, markdown), but the pptx and xlsx readers only exist in recent releases; the apt package can be older.
Check, and if either is missing install the `.deb` from pandoc's GitHub releases (3.x) instead:

```bash
pandoc --list-input-formats | grep -E '^(pptx|xlsx)$'   # both must be listed
```

## 3. PHP extensions

Forge's PHP build normally has them all; verify on the CLI version the site uses:

```bash
php -m | grep -Ei '^(pdo_pgsql|pgsql|dom|xml|mbstring|intl|zip|bcmath)$'
```

`predis` is plain PHP, so the `redis` extension is optional (`REDIS_CLIENT=predis` or `phpredis` both work).

## 4. Environment (`.env` in Forge)

Start from `.env.example` and change at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain

DB_CONNECTION=pgsql
DB_DATABASE=forge
DB_USERNAME=forge
DB_PASSWORD=…           # Forge shows it once when the server is created

QUEUE_CONNECTION=redis  # Horizon only works redis queues
REDIS_CLIENT=phpredis   # or predis

MAIL_MAILER=resend      # Resend's API (resend/resend-php): password resets, invites, contact form
RESEND_API_KEY=…        # the MAIL_HOST/PORT/USERNAME/PASSWORD SMTP settings are then unused
MAIL_FROM_ADDRESS=…     # on a domain verified in Resend

OPENAI_API_KEY=…        # without it documents are still extracted and text-searchable

GOOGLE_CLIENT_ID=…      # Gmail — see docs/gmail.md
GOOGLE_CLIENT_SECRET=…
GOOGLE_REDIRECT_URI="${APP_URL}/settings/mailboxes/gmail/callback"
GOOGLE_PUBSUB_TOPIC=…
GOOGLE_PUBSUB_AUDIENCE="${APP_URL}/webhooks/gmail"
GOOGLE_PUBSUB_SERVICE_ACCOUNT=…

KNOWLEDGE_DISK=knowledge   # or s3 for an EU bucket (configure the s3 disk)
```

The `knowledge` disk lives in `storage/app/private/knowledge`. With Forge's zero-downtime deployments
`storage/` is shared between releases, so originals survive a deploy.

## 5. Deploy script

`.node-version` pins the Node version; CI reads it via `node-version-file`, and the server must run the same
major (vite-plus needs at least 22.18 or 24.11). Forge may provision an older Node, so check with `node -v` and,
if needed, install Node 24 from NodeSource (it ships npm 11):

```bash
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -
sudo apt install -y nodejs
node -v
```

The project uses npm: `package-lock.json` is the only lockfile, so deploy with `npm ci`, never pnpm or
`npm install`. `npm run build` also runs `php artisan wayfinder:generate` through the Wayfinder Vite plugin,
so it needs PHP and a working `.env`.

The deploy script stops before touching anything when the server's Node major does not match `.node-version`;
with zero-downtime deployments the failed release is never activated.

```bash
cd $FORGE_RELEASE_DIRECTORY   # or $FORGE_SITE_PATH without zero-downtime

REQUIRED_NODE="$(cut -d. -f1 .node-version)"
if [ "$(node -p 'process.versions.node.split(".")[0]')" != "$REQUIRED_NODE" ]; then
    echo "Node $REQUIRED_NODE required (.node-version), found $(node -v)"
    exit 1
fi

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize
$FORGE_PHP artisan horizon:terminate   # the daemon restarts Horizon on the new code
```

## 6. Horizon daemon

Forge → site → **Daemons** (or the Horizon toggle in the site's Laravel panel):

- Command: `php artisan horizon`
- Directory: `/home/forge/<site>/current` (zero-downtime) or `/home/forge/<site>`
- User: `forge`
- Stop wait: at least `900` seconds, so a running knowledge job can finish

Horizon runs two supervisors (`config/horizon.php`): `default` for mail sync and imports, `knowledge` for
document processing. Keep `retry_after` (960s, `config/queue.php`) above the knowledge jobs' 900s timeout.
The dashboard at `/horizon` is open to super-admins only. The same applies to
`/log-viewer` (opcodesio/log-viewer): enabled in production, session-authenticated,
and gated on `users.is_admin`.

## 7. Scheduler

Not part of the deploy script: the schedule itself lives in `routes/console.php`, and the server only needs one
cron entry. Either toggle **Scheduler** in the site's Laravel panel, or add a scheduled job under Forge → server
→ **Scheduled jobs**:

- Command: `php /home/forge/<site>/current/artisan schedule:run` (drop `/current` without zero-downtime)
- User: `forge`, not root, so files in `storage/` stay writable for php-fpm and Horizon
- Frequency: every minute

Use one of the two, not both. It runs `mailboxes:renew-watches` (daily — Gmail watches expire after 7 days),
`mailboxes:poll` and `knowledge:poll-batches` (every five minutes), and `knowledge:prune-ai-payloads` (daily —
removes stored AI prompts and responses after `AI_LOG_PAYLOAD_RETENTION_DAYS`, default 30).

## 8. Optional: Inertia SSR

`config/inertia.php` has SSR enabled. Without a running SSR server Inertia falls back to client-side
rendering, so this is optional. To turn it on: build with `npm run build:ssr` instead of `npm run build`, add
a daemon `php artisan inertia:start-ssr`, and add `$FORGE_PHP artisan inertia:stop-ssr` to the deploy script.

## 9. Gmail

The Google Cloud side (OAuth client, Pub/Sub topic and push subscription) is in `docs/gmail.md`. In
production, point the push subscription at `https://your-domain/webhooks/gmail` and add the production
callback URL to the OAuth client.

## Checklist after the first deploy

```bash
sudo -u postgres psql -d forge -c '\dx'        # vector, ltree
php artisan migrate:status                      # all Ran
php artisan horizon:status                      # running
php artisan schedule:list                       # four commands
```

Then upload a PDF and a DOCX in the knowledge base and check that both are processed and wait for approval.

Once, after deploying the read-once release on a server that already interpreted mail, remember what was read
before it, so forwards of older mail are recognised too:

```bash
php artisan emails:fingerprint
```

Once, after deploying the release with one knowledge base language: an account whose knowledge was built from
documents in several languages gets it into its own language (facts and summaries; `--folders` for folder names
too). It uses the AI provider, roughly one light-model call per 25 facts:

```bash
php artisan knowledge:translate {account} --queue
```
