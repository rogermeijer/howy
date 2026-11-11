# Multi-tenancy and localization

How the tenancy boundary is enforced, why it is built this way, and what to do when it throws.
The non-negotiable rules live in `CLAUDE.md`; this document explains the machinery behind them.

## The data model

| table          | role                                                                                        |
| -------------- | ------------------------------------------------------------------------------------------- |
| `accounts`     | the tenant. Owns every row of business data. Carries the account's `locale` and `timezone`. |
| `users`        | a person. Can belong to several accounts.                                                   |
| `account_user` | membership. Holds `role` (a slug, currently always null) and `is_admin`.                    |

`users.active_account_id` is **a pointer, not the truth**. It records where someone was last working so
they land back there, but it can go stale — a membership can be revoked, an account can be deleted
(the FK is `nullOnDelete`). Membership in `account_user` is the truth, and `SetTenantContext` always
resolves the active account _through the membership relation_ so a stale pointer can never grant access.

Two `is_admin` flags with deliberately different reach:

- `users.is_admin` — platform super-admin. Overrides everything, everywhere.
- `account_user.is_admin` — admin of one account. Overrides everything inside it only.

They are one path segment apart (`$user->is_admin` versus `$user->accounts->first()->pivot->is_admin`),
so always read them through `isSuperAdmin()` and `isAdminOf()`.

## Context lifecycle

`App\Tenancy\TenantContext` holds the current account. It is bound **scoped**, not singleton:

```php
$this->app->scoped(TenantContext::class);   // AppServiceProvider::register()
```

That matters because `QueueServiceProvider` calls `$app->forgetScopedInstances()` between jobs
(`vendor/laravel/framework/src/Illuminate/Queue/QueueServiceProvider.php:263`). A queued job therefore
starts with **no** context by construction and cannot inherit whatever ran before it. A static property
would not have that property, which is exactly the silent cross-tenant leak this design exists to stop.

Reach it through the `Tenancy` facade, never by resolving the class.

### Middleware order

In `bootstrap/app.php` the `web` group gains, in this order:

1. `SetTenantContext` — resolves the account, self-heals a stale pointer, sets the context. Never fails.
2. `SetLocale` — resolves language and timezone (it may read the account, hence the order).
3. `HandleInertiaRequests` — shares the account, the memberships, the locale and the catalogue.

Plus one priority rule that is easy to miss and expensive to get wrong:

```php
$middleware->prependToPriorityList(SubstituteBindings::class, SetTenantContext::class);
```

`SubstituteBindings` is part of the `web` group, and appended middleware run _after_ it. Without this
line, route-model binding on a tenant model resolves **before** there is a context, and every such
route throws `TenantContextMissingException` instead of scoping. This was a real bug caught by
`TenantRouteBindingTest`; do not remove it.

`RequireTenantContext` is a separate, aliased middleware (`tenant`) that 403s when no account is
current. It gates the app routes and the account settings routes, and is **deliberately absent** from
the profile routes: someone who somehow ends up with no membership must still reach their profile,
their password and the logout button.

## `BelongsToAccount`

```php
#[ScopedBy(AccountScope::class)]
trait BelongsToAccount { ... }
```

`HasGlobalScopes::resolveGlobalScopeAttributes()` reflects over **directly-used traits**
(`.../Eloquent/Concerns/HasGlobalScopes.php:36-40`), which is why a bare `use BelongsToAccount;` is
enough — and also why nesting the trait inside another trait silently loses the scope.
`TenantSchemaGuardTest` asserts with `class_uses()` rather than `class_uses_recursive()` for that reason.

`AccountScope::apply()` uses `$model->qualifyColumn('account_id')`. Without qualification, any join
against another table that also has `account_id` produces an ambiguous-column SQL error instead of a
filter.

### What `creating` does

| tenancy state               | `account_id` on the model | result                                    |
| --------------------------- | ------------------------- | ----------------------------------------- |
| disabled (`withoutTenancy`) | null                      | **throw** `TenantContextMissingException` |
| disabled                    | set                       | allowed as-is                             |
| no context                  | anything                  | **throw** `TenantContextMissingException` |
| context set                 | null                      | filled from the context                   |
| context set                 | equals the context        | allowed                                   |
| context set                 | differs                   | **throw** `CrossTenantWriteException`     |

The last row is what catches `Email::create($request->all())` with a hostile `account_id`. An
`updating` guard additionally throws when `account_id` is dirty — a record never changes owner.

## When it throws

**`TenantContextMissingException`** — you touched tenant data with no current account. Almost always
console, queue or seeder code. Wrap the work:

```php
Tenancy::for($account, fn () => Email::query()->latest()->get());
```

**`CrossTenantWriteException`** — you tried to write a row into a different account than the current
one. Either an `account_id` came from user input (fix that), or the write is genuinely meant for
another account, in which case wrap it in `Tenancy::for()`.

Neither is mapped to an HTTP status. They are programming errors and a 500 is the point.
**The fix is never to remove the scope.**

## Recipes

```php
// One account
Tenancy::for($account, fn () => /* ... */);

// Every account
Account::query()->each(fn (Account $a) => Tenancy::for($a, fn () => /* ... */));

// Genuinely cross-account read (admin tooling only)
Tenancy::withoutTenancy(fn () => Email::query()->count());
```

### The one request-path exception: the Gmail webhook

`GmailWebhookController` is the only code in a request path allowed to call `withoutTenancy()`. A Gmail
push arrives through Cloud Pub/Sub with no session, carrying only the mailbox's email address. Working out
which account owns that address has to look across accounts, just like the membership lookup that makes
`account_user` unscoped.

The bypass is kept as small as possible:

- It is one read of `[id, account_id]` pairs from `mailboxes`, and nothing else.
- Every pair is then handled inside `Tenancy::for($accountId, …)`, which loads the mailbox again through
  the normal scope.
- The request is authenticated first, by the Google-signed OIDC token on the push.

`tests/Feature/Tenancy/WithoutTenancyGuardTest.php` pins the exception to that single file, so any other
call under `app/` fails CI. Adding a second exception needs a written reason in that test and in CLAUDE.md.

### The second source of an account: a link in a mail

Pages opened from a link in one of Howy's mails (rating an answer, looking over what a mail added,
unsubscribing) have no session: whoever opens them usually has no account. Their account comes from the
link itself, which looks like it breaks rule 7, "never trust an `account_id` from user input". It does not,
and this is why:

- The link carries a token, not an id. `App\Services\Mail\MailLinks` makes it: the account, what the link
  opens, whom it went to, its language and until when it works, as JSON, encrypted and authenticated with
  AES-256-GCM under a key derived from the app key. Only this app can make one or read one; a changed byte does
  not decrypt.
- `ResolveTenantFromMailLink` (alias `mail-link`, optionally with the purpose a route expects) decrypts it and
  calls `Tenancy::set()` with that account. A token this app did not issue, or one for another page, 404s.
- After that everything goes through the normal scope: the record the link names is loaded with a scoped
  query, so one that is not the account's 404s. There is no `withoutTenancy()` anywhere in it.
- A form on such a page never posts an account or a record id: both come from the token in the URL.

`tests/Feature/Tenancy/TenantSourceGuardTest.php` pins `Tenancy::set()` to `SetTenantContext` and this
middleware, so a third way to pick an account fails CI. Adding one needs a written reason there and in
CLAUDE.md. A link expires after `MAIL_LINK_HOURS` (48), except the one to unsubscribe; an expired one offers a
new link by mail, sent only to whom the old one went, from the mailbox the mail came from.

### Broadcasting: one channel per account, topics only

Open pages follow along over Laravel Reverb (a mail that went shows in the log without a refresh). A socket is
a second way for data to leave the app, so it carries none:

- There is one channel, the private `account.{id}` in `routes/channels.php`. Only a member of that account may
  listen (`$user->belongsToAccount()`, which reads `account_user`, the one table meant to be read across
  accounts). There are no user, model or presence channels.
- What goes over it is `App\Events\AccountChanged`: an account id and an `AccountTopic`, nothing else. A page
  that hears its topic does a partial Inertia reload (`useAccountUpdates()`), so the data still comes through
  the normal request path, under the session's tenant and the global scope. A listener on the wrong channel
  would learn that something changed, never what.
- It is sent from the model that changed, with the record's own `account_id`, after the change is committed,
  and queued, so a socket server that is down never holds up a mail. It is unique per account and topic while
  it waits in the queue: a burst goes out once, and a change after it went out sends another.
- The topics: `mail` (mail came in, other than an import; an interpretation saved), `sent-mails` (a logged mail
  created or moved to another status), `approvals` (`needs_review` changed, or a document version came to or
  left review, or was approved). The inbox, a mail, the colleagues, the mail log, the approvals and the count in
  the top bar listen.

A new channel, or an event that carries data, needs a written reason here and in CLAUDE.md.
`tests/Feature/Tenancy/AccountChannelTest.php` holds the line. Locally `php artisan dev` starts Reverb when
`BROADCAST_CONNECTION=reverb`; on Forge, Reverb is switched on per site.

### The one cross-account unique index: an active Gmail address

A Gmail address is active in one account at most. Revoking a grant at Google, which disconnecting does,
revokes it for every account holding that address, so a second active copy would be unlinked without its
admins doing anything. `mailboxes_one_active_per_address` enforces this as a partial unique index on
`(provider, email_address) WHERE status = 'active'`:

- Partial, because disconnected and needs-reauth mailboxes keep their row and their emails. They never
  block connecting the address elsewhere.
- In the database rather than a lookup, so the check needs no `withoutTenancy()` in a request path, and
  two accounts connecting at the same moment cannot both win.
- `MailboxConnector::connect()` saves in its own transaction and turns the violation into
  `MailboxConnectedElsewhereException`. The OAuth callback shows a toast. Nothing is revoked on refusal:
  the grant Google just handed out is the one the other account is using.

A queued job:

```php
class SyncInbox implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public function __construct(public int $emailId)
    {
        $this->rememberTenant();
    }
}
```

`$tenantAccountId` is typed non-nullable and uninitialised, so a job that forgets `rememberTenant()`
fails at serialisation rather than running unscoped.

**Pass ids, not tenant models.** `Queueable` includes `SerializesModels`, and the worker unserialises
the job (re-fetching any models) _before_ job middleware runs. So a tenant model in a job property is
fetched with no account set, and throws `TenantContextMissingException`. Store the id and load the model
in `handle()`, where `RunInTenantContext` has already set the account. The mailbox jobs in `app/Jobs/`
do exactly this.

In tests, `Tests\TestCase::actingAsMember()` gives you a signed-in user with an account.

## What the CI guards do and do not catch

`tests/Feature/Tenancy/TenantSchemaGuardTest.php` is schema-driven rather than a hand-maintained list,
because reflection alone cannot notice a _migration_ that adds `account_id` to a table whose model
someone forgot to update. It asserts:

- every table with an `account_id` column has a model that uses the trait (exempting `account_user`);
- every model using the trait sits on a table that has the column;
- nothing under `app/` contains `withoutGlobalScope`.

`WithoutTenancyGuardTest` separately asserts that the only `withoutTenancy()` calls under `app/` are the
written exceptions, and `TenantSourceGuardTest` that only `SetTenantContext` and `ResolveTenantFromMailLink`
call `Tenancy::set()`.

It does **not** catch a tenant column named anything other than `account_id`, a hand-written
`Route::bind()` closure, an overridden `resolveRouteBinding()`, or raw SQL. Those are covered by rules,
not by the machine.

## Localization

Resolution, first match wins:

|           | chain                                                        |
| --------- | ------------------------------------------------------------ |
| signed in | `users.locale` → `accounts.locale` → `config('app.locale')`  |
| guest     | `locale` cookie → `Accept-Language` → `config('app.locale')` |

Timezone follows the same shape. A `null` user column means "follow the account", which is why those
columns are nullable — the fallback needs no separate sentinel. The settings form surfaces it as an
explicit "Follow account" option so the inheritance is visible rather than hidden behind a blank field.

The `locale` cookie is unencrypted (it joins `sidebar_state` in `encryptCookies(except:)`) and is
written on every switch, including for signed-in users, so the cookie and the column can never
disagree and the choice survives logging out.

**Timezones are not applied to PHP.** `config('app.timezone')` stays UTC and
`date_default_timezone_set()` is never called per request: doing so would make `now()` local and
Eloquent would then write local times into UTC-assumed columns. Timestamps go out as UTC and
`useFormatDate()` converts them with `Intl.DateTimeFormat` at the display boundary.

Translations come from Laravel's own loader and ship to React as an Inertia prop, so PHP and the
frontend share one catalogue. Keys are English source strings, so `lang/en.json` does not exist and a
missing Dutch translation falls back to readable English rather than a raw key — which is what makes a
partial sweep safe.

Framework messages (validation, auth, passwords) are generated by `laravel-lang/common`, a dev
dependency: `php artisan lang:add nl`, `php artisan lang:update`. It preserves custom keys in
`lang/nl.json`, so application strings live there safely alongside them.

## Roles and permissions

`app/Enums/Role.php` and `app/Enums/Permission.php` exist and are **inert**: nothing reads them, no
gates or policies are registered, and `account_user.role` is null on every row.

They are defined in code rather than in tables because there is no roles administration screen — rows
would only be a lookup table kept in sync with these constants by a seeder, which is two sources of
truth for one fact. The pivot column casts to the enum, which validates the value at the type level and
is visible to PHPStan; that is stronger than a foreign key, at the cost of ruling out per-account
custom roles until tables are introduced.

When authorization is switched on, `Role::permissions()` is the seam. Remember that both `is_admin`
flags override it entirely.
