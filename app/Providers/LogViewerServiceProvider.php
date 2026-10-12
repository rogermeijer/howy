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
    }

    /**
     * Log files can contain secrets from every account, so only platform
     * super-admins may open the viewer — including in production.
     */
    protected function gate(): void
    {
        Gate::define('viewLogViewer', fn ($user = null): bool => $user instanceof User && $user->isSuperAdmin());
    }
}
