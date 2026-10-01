<?php

namespace App\Services\Knowledge\Search;

/**
 * Finds what the knowledge base says about a question, for the current account
 * only: passages with their exact source, plus matching facts, within a hard
 * token budget so the result can go straight into a prompt.
 */
interface KnowledgeSearch
{
    public function search(SearchQuery $query): SearchResult;
}
