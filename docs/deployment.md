# Deployment (Laravel Forge / Ubuntu VPS)

What a fresh Forge server needs before Howy deploys and runs. Forge provisions PHP, Nginx, Node, Redis and
PostgreSQL; the items below are what Howy adds on top. Commands run over SSH as the `forge` user unless noted.

| Needed for                              | What                                                 | Forge installs it?        |
| --------------------------------------- | ---------------------------------------------------- | ------------------------- |
| Embeddings, hybrid search               | PostgreSQL extension `vector` (pgvector)             | No                        |
| Topic folder tree                       | PostgreSQL extension `ltree` (contrib)               | Package yes, extension no |
| PDF extraction                          | `pdftohtml`, `pdfinfo`, `pdftoppm` (`poppler-utils`) | No                        |
| Scanned-PDF fallback                    | `qpdf`                                               | No                        |
| DOCX extraction                         | `pandoc`                                             | No                        |
| Queues (`default`, `knowledge`)         | Redis + Horizon daemon                               | Redis yes, daemon no      |
| Gmail watch renewal, polling, Batch API | Scheduler (`schedule:run` every minute)              | No (toggle)               |
| Frontend build                          | Node 22                                              | Check version             |

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

MAIL_MAILER=…           # real mailer: password resets, invites, contact form
MAIL_FROM_ADDRESS=…

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

Node must be 20.19+ (Vite 8); use 22, like CI. `npm run build` also runs `php artisan wayfinder:generate`
through the Wayfinder Vite plugin, so it needs PHP and a working `.env`.

```bash
cd $FORGE_RELEASE_DIRECTORY   # or $FORGE_SITE_PATH without zero-downtime

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

Forge → site → **Scheduler** (or the toggle in the Laravel panel): `php artisan schedule:run` every minute.
It runs `mailboxes:renew-watches` (daily — Gmail watches expire after 7 days), `mailboxes:poll` and
`knowledge:poll-batches` (every five minutes).

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
php artisan schedule:list                       # three commands
```

Then upload a PDF and a DOCX in the knowledge base and check that both are processed.
