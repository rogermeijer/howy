<?php

namespace App\Jobs\Knowledge;

use App\Services\Knowledge\Translation\KnowledgeTranslation;
use App\Tenancy\Concerns\InteractsWithTenancy;
use App\Tenancy\Contracts\TenantAware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Puts what the knowledge base holds into its language, after an
 * administrator changed it. Safe to run again: what is in the language
 * already is left alone.
 */
class TranslateKnowledge implements ShouldQueue, TenantAware
{
    use InteractsWithTenancy, Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    /**
     * @param  bool  $folders  folder names and descriptions too
     */
    public function __construct(public bool $folders = true)
    {
        $this->rememberTenant();
        $this->onQueue((string) config('knowledge.queue'));
    }

    public function handle(KnowledgeTranslation $translation): void
    {
        $translation->run($this->folders);
    }
}
