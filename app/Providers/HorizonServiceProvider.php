<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Horizon shows every account's jobs, so outside local only platform
     * super-admins may open it.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null): bool => $user instanceof User && $user->isSuperAdmin());
    }
}
