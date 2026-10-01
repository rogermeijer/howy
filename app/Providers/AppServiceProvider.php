<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopic;
use App\Models\User;
use App\Services\Knowledge\Extraction\NullScannedPageReader;
use App\Services\Knowledge\Extraction\ScannedPageReader;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        $this->app->bind(ScannedPageReader::class, NullScannedPageReader::class);
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

        // Short, stable names in *_type columns instead of class names, so a
        // renamed class never orphans knowledge rows.
        Relation::enforceMorphMap([
            'account' => Account::class,
            'user' => User::class,
            'email' => Email::class,
            'document' => Document::class,
            'document_version' => DocumentVersion::class,
            'document_section' => DocumentSection::class,
            'knowledge_fact' => KnowledgeFact::class,
            'knowledge_topic' => KnowledgeTopic::class,
        ]);

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
