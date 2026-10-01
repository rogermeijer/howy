<?php

namespace App\Providers;

use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped rather than singleton: the queue worker forgets scoped instances
        // between jobs, so a job can never inherit the previous job's account.
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerDevCommands();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function registerDevCommands(): void
    {
        if (! app()->runningInConsole()) {
            return;
        }

        $domain = config('services.ngrok.domain');

        if (! is_string($domain) || $domain === '') {
            return;
        }

        $port = config('services.ngrok.port');

        DevCommands::register(
            sprintf('ngrok http %d --url=%s --log=stdout', $port, $domain),
            'ngrok',
        )->green();
    }
}
