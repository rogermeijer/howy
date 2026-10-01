<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Services\Knowledge\Search\FactHit;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\Passage;
use App\Services\Knowledge\Search\SearchQuery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Ask the knowledge base a question from the terminal, to see exactly what
 * retrieval returns: passages with their source, ranks and token count.
 */
#[Signature('knowledge:search {account : Account id} {question : What to search for} {--tokens= : Token budget}')]
#[Description('Search an account\'s knowledge base and show the passages and facts it returns')]
class SearchKnowledge extends Command
{
    public function handle(KnowledgeSearch $search): int
    {
        $account = Account::query()->findOrFail((int) $this->argument('account'));

        $result = Tenancy::for($account, fn () => $search->search(new SearchQuery(
            text: (string) $this->argument('question'),
            maxTokens: $this->option('tokens') ? (int) $this->option('tokens') : null,
        )));

        $this->components->info(sprintf(
            '%d passages, %d facts, ~%d tokens%s',
            count($result->passages),
            count($result->facts),
            $result->tokenCount,
            $result->usedVectors ? '' : ' (full-text only)',
        ));

        foreach ($result->facts as $fact) {
            /** @var FactHit $fact */
            $this->line(sprintf('  <fg=magenta>[%s]</> %s <fg=gray>(%s)</>', $fact->status->value, $fact->statement, $fact->headingPath));
        }

        foreach ($result->passages as $index => $passage) {
            /** @var Passage $passage */
            $this->newLine();
            $this->line(sprintf(
                '<options=bold>%d. %s</> <fg=gray>score %.4f · vector #%s · text #%s · %d tokens%s</>',
                $index + 1,
                $passage->citation(),
                $passage->score,
                $passage->ranks['vector'] ?? '–',
                $passage->ranks['text'] ?? '–',
                $passage->tokenCount,
                $passage->wholeSection ? ' · whole section' : '',
            ));
            $this->line($passage->text);
        }

        return self::SUCCESS;
    }
}
