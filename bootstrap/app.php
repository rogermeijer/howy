<?php

use App\Http\Middleware\ForwardTunnelledOAuthCallback;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireAccountAdmin;
use App\Http\Middleware\RequireSetUpAccount;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\RequireTenantContext;
use App\Http\Middleware\ResolveTenantFromMailLink;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The locale cookie joins sidebar_state as an unencrypted UI preference.
        $middleware->encryptCookies(except: ['sidebar_state', SetLocale::COOKIE]);

        $middleware->prepend(ForwardTunnelledOAuthCallback::class);

        $middleware->alias([
            'tenant' => RequireTenantContext::class,
            'account.admin' => RequireAccountAdmin::class,
            'set-up' => RequireSetUpAccount::class,
            'super-admin' => RequireSuperAdmin::class,
            'mail-link' => ResolveTenantFromMailLink::class,
        ]);

        $middleware->web(append: [
            SetTenantContext::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Appended middleware would otherwise run *after* SubstituteBindings, and
        // route-model binding on a tenant model needs the account already resolved
        // or it throws instead of scoping. Priority pulls it in front.
        $middleware->prependToPriorityList(SubstituteBindings::class, SetTenantContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
