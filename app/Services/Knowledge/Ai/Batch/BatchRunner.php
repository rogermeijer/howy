<?php

namespace App\Services\Knowledge\Ai\Batch;

/**
 * Runs prompts asynchronously at the provider's batch discount, for work no
 * one is waiting on: summaries and facts. Results are collected by polling.
 */
interface BatchRunner
{
    /**
     * @param  list<BatchRequest>  $requests  at least one
     * @return string the provider's batch id
     */
    public function submit(array $requests): string;

    public function status(string $batchId): BatchStatus;
}
