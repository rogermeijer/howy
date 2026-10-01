<?php

namespace App\Providers;

use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Middleware\TrustProxies;
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

        // Local tunnels (ngrok) sit in front of the app; trust them so URLs keep
        // their https scheme. Set here rather than in bootstrap/app.php, where
        // config is not loaded yet.
        if (app()->environment('local')) {
            TrustProxies::at('*');
        }

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
