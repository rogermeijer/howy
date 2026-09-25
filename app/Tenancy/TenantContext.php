<?php

namespace App\Tenancy;

use App\Exceptions\TenantContextMissingException;
use App\Models\Account;
use Closure;

/**
 * Holds the account the current request, job or console block is operating on.
 *
 * Bound as a *scoped* instance rather than a singleton: the queue worker calls
 * forgetScopedInstances() between jobs, so a job can never inherit the context of
 * whatever ran before it. A static property would not have that property, which is
 * exactly the silent cross-tenant leak this class exists to prevent.
 *
 * Reach for the Tenancy facade rather than resolving this directly.
 */
class TenantContext
{
    protected ?Account $account = null;

    protected bool $disabled = false;

    public function set(Account $account): void
    {
        $this->account = $account;
    }

    public function forget(): void
    {
        $this->account = null;
    }

    public function check(): bool
    {
        return $this->account !== null;
    }

    public function account(): Account
    {
        return $this->account ?? throw TenantContextMissingException::forQuery();
    }

    public function id(): int
    {
        return $this->account()->id;
    }

    public function idOrNull(): ?int
    {
        return $this->account?->id;
    }

    public function disabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Run a callback with a different account current, restoring whatever was there
     * before — including when the callback throws, so a failure cannot leave the
     * rest of the request scoped to the wrong account.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function for(Account|int $account, Closure $callback): mixed
    {
        $previous = $this->account;

        $this->account = $account instanceof Account
            ? $account
            : Account::query()->findOrFail($account);

        try {
            return $callback();
        } finally {
            $this->account = $previous;
        }
    }

    /**
     * Run a callback with account scoping switched off entirely.
     *
     * The only sanctioned bypass. Creating still requires an explicit account_id,
     * so this opens up reads across accounts without also making writes ambiguous.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withoutTenancy(Closure $callback): mixed
    {
        $previous = $this->disabled;

        $this->disabled = true;

        try {
            return $callback();
        } finally {
            $this->disabled = $previous;
        }
    }
}
