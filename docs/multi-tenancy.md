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
fails at serialisation rather than running unscoped. Note that a job using `SerializesModels` with a
tenant model re-fetches it on unserialize, _before_ `handle()` — with the trait the context is restored
first; without it the fetch throws. That is the intended behaviour.

In tests, `Tests\TestCase::actingAsMember()` gives you a signed-in user with an account.

## What the CI guards do and do not catch

`tests/Feature/Tenancy/TenantSchemaGuardTest.php` is schema-driven rather than a hand-maintained list,
because reflection alone cannot notice a _migration_ that adds `account_id` to a table whose model
someone forgot to update. It asserts:

- every table with an `account_id` column has a model that uses the trait (exempting `account_user`);
- every model using the trait sits on a table that has the column;
- nothing under `app/` contains `withoutGlobalScope`.

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
