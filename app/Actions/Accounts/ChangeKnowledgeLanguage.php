<?php

namespace App\Actions\Accounts;

use App\Enums\Locale;
use App\Jobs\Knowledge\TranslateKnowledge;
use App\Models\Account;

/**
 * Sets the language the knowledge base is built in, from settings and from
 * setup alike. Changed, what it holds is put into the new language in the
 * background; an empty knowledge base has nothing to put there.
 */
class ChangeKnowledgeLanguage
{
    /**
     * @return bool whether the language changed
     */
    public function handle(Account $account, Locale|string $locale): bool
    {
        $before = $account->knowledge_locale;
        $account->forceFill(['knowledge_locale' => $locale])->save();

        if ($account->knowledge_locale === $before) {
            return false;
        }

        TranslateKnowledge::dispatch(folders: true);

        return true;
    }
}
