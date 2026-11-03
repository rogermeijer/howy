<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class LogViewerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->gate();
        $this->configureApiStatefulDomains();
    }

    /**
     * Log files can contain secrets from every account, so only platform
     * super-admins may open the viewer — including in production.
     */
    protected function gate(): void
    {
        Gate::define('viewLogViewer', fn ($user = null): bool => $user instanceof User && $user->isSuperAdmin());
    }

    /**
     * Log Viewer's API uses Sanctum-style stateful detection (Referer / Origin).
     * When config leaves domains null, the package falls back to APP_URL; add ngrok
     * explicitly so the same session works when browsing via a tunnel.
     */
    protected function configureApiStatefulDomains(): void
    {
        $ngrokDomain = config('services.ngrok.domain');

        if (! is_string($ngrokDomain) || $ngrokDomain === '') {
            return;
        }

        $extra = [$ngrokDomain];

        $ngrokPort = config('services.ngrok.port');

        if (is_numeric($ngrokPort) && ! in_array((int) $ngrokPort, [80, 443], true)) {
            $extra[] = $ngrokDomain.':'.$ngrokPort;
        }

        $configured = config('log-viewer.api_stateful_domains');

        if (! is_array($configured)) {
            $configured = [];
            $appUrl = config('app.url');

            if (is_string($appUrl) && $appUrl !== '') {
                $host = parse_url($appUrl, PHP_URL_HOST);
                $port = parse_url($appUrl, PHP_URL_PORT);

                if (is_string($host) && $host !== '') {
                    $configured[] = $host.($port ? ':'.$port : '');
                }
            }
        }

        config([
            'log-viewer.api_stateful_domains' => array_values(array_unique(array_merge($configured, $extra))),
        ]);
    }
}
