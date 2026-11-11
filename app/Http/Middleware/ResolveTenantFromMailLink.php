<?php

namespace App\Http\Middleware;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Services\Mail\MailLinks;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The account for a page opened from a link in one of Howy's mails, without
 * logging in: the one in the link's token. That token is made and encrypted by
 * this app alone (MailLinks), so the account in it is no account id from user
 * input, which is never trusted (CLAUDE.md rule 7, "Mail links" in
 * docs/multi-tenancy.md). Records are read through the account's scope after
 * this, so one that is not the account's is not found. A token this app did
 * not issue, or for another page, is not found either.
 */
class ResolveTenantFromMailLink
{
    public function __construct(private readonly MailLinks $links) {}

    public function handle(Request $request, Closure $next, ?string $purpose = null): Response
    {
        $link = $this->links->decode((string) $request->route('token'));

        abort_if($link === null || ($purpose !== null && $link->purpose->value !== $purpose), 404);

        $account = Account::query()->find($link->accountId);

        abort_if($account === null, 404);

        Tenancy::set($account);
        $request->attributes->set('mail_link', $link);

        return $next($request);
    }
}
