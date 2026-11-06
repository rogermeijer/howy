<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Jobs\Knowledge\TranslateKnowledge;
use App\Models\Account;
use App\Services\Knowledge\Translation\KnowledgeTranslation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Puts an account's knowledge base into its language: facts and summaries
 * written in another one, and with --folders the folder names too. Run once
 * for knowledge built before the knowledge base had one language; safe to
 * rerun.
 */
#[Signature('knowledge:translate {account : Account id} {--folders : Also translate folder names and descriptions} {--queue : Queue it instead of running it now}')]
#[Description('Translate what the knowledge base holds into its language')]
class TranslateKnowledgeCommand extends Command
{
    public function handle(KnowledgeTranslation $translation): int
    {
        $account = Account::query()->findOrFail((int) $this->argument('account'));

        return Tenancy::for($account, function () use ($account, $translation): int {
            if ($this->option('queue')) {
                TranslateKnowledge::dispatch((bool) $this->option('folders'));
                $this->components->info('Queued.');

                return self::SUCCESS;
            }

            $this->components->info("Translating into {$account->knowledge_locale->label()}…");
            $result = $translation->run((bool) $this->option('folders'));

            $this->table(['Facts', 'Summaries', 'Folders', 'Merged', 'Versions checked again'], [array_values($result)]);

            return self::SUCCESS;
        });
    }
}
