<?php

namespace App\Services\Knowledge\Ai;

use App\Models\AiUsageRecord;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Files\File;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * The one way out to an AI provider. Every call goes through here, so this is
 * where we decide what may leave the application and where every token is
 * accounted to the account and the step that spent it.
 *
 * What is sent: document text (chunks, sections, whole documents for context
 * lines) and, for scanned pages, the pages themselves. Never account or user
 * names, email addresses or ids. With OPENAI_STORE=false OpenAI keeps no copy
 * for later retrieval; OpenAI does not train on API data.
 */
class AiGateway
{
    public function enabled(): bool
    {
        return filled(config('ai.providers.'.$this->provider().'.key'));
    }

    /**
     * Embed texts, in input order.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts, string $step, ?Model $subject = null): array
    {
        $this->ensureEnabled();

        if ($texts === []) {
            return [];
        }

        $provider = (string) config('knowledge.embeddings.provider');
        $model = (string) config('knowledge.embeddings.model');
        $vectors = [];

        // OpenAI takes up to 2048 inputs a request; stay well below the token cap too.
        foreach (array_chunk($texts, 64) as $batch) {
            $response = Embeddings::for($batch)
                ->dimensions((int) config('knowledge.embeddings.dimensions'))
                ->timeout(120)
                ->generate($provider, $model);

            $this->record($step, $subject, $provider, $model, $response->usage->inputTokens, 0, 0);

            foreach ($response->embeddings as $vector) {
                $vectors[] = array_values(array_map(floatval(...), $vector));
            }
        }

        return $vectors;
    }

    /**
     * Run an agent and return its structured result.
     *
     * @param  list<File>  $attachments
     * @return array<string, mixed>
     */
    public function structured(Agent $agent, string $prompt, string $step, ?Model $subject = null, array $attachments = []): array
    {
        $response = $this->prompt($agent, $prompt, $step, $subject, $attachments);

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The agent did not return structured output.');
        }

        /** @var array<string, mixed> $structured */
        $structured = $response->structured;

        return $structured;
    }

    /**
     * @param  list<File>  $attachments
     */
    public function prompt(Agent $agent, string $prompt, string $step, ?Model $subject = null, array $attachments = []): AgentResponse
    {
        $this->ensureEnabled();

        $provider = $this->provider();
        $model = $this->modelFor($step);

        /** @var AgentResponse $response */
        $response = $agent->prompt($prompt, $attachments, $provider, $model, timeout: 300);

        $usage = $response->usage;
        $this->record(
            $step,
            $subject,
            $provider,
            $model,
            $usage->inputTokens,
            (int) $usage->cacheReadInputTokens,
            $usage->outputTokens,
        );

        return $response;
    }

    /**
     * Record usage of work done elsewhere, e.g. a batch whose results came in.
     */
    public function record(string $step, ?Model $subject, string $provider, string $model, int $input, int $cached, int $output, bool $batch = false): void
    {
        AiUsageRecord::create([
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'step' => $step,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $input,
            'cached_input_tokens' => $cached,
            'output_tokens' => $output,
            'is_batch' => $batch,
            'estimated_cost_micros' => $this->costMicros($model, $input, $cached, $output, $batch),
        ]);
    }

    public function modelFor(string $step): string
    {
        return (string) config("knowledge.llm.models.{$step}", config('ai.providers.'.$this->provider().'.models.text.default'));
    }

    public function provider(): string
    {
        return (string) config('knowledge.llm.provider');
    }

    /**
     * Estimated cost in millionths of a dollar. Prices are per million tokens,
     * so a token costs exactly its price in micro-dollars.
     */
    public function costMicros(string $model, int $input, int $cached, int $output, bool $batch = false): int
    {
        /** @var array{input: float, cached_input: float, output: float}|null $price */
        $price = config("knowledge.pricing.{$model}");

        if ($price === null) {
            return 0;
        }

        $cost = ($input - $cached) * $price['input'] + $cached * $price['cached_input'] + $output * $price['output'];

        return (int) round($batch ? $cost / 2 : $cost);
    }

    private function ensureEnabled(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('No AI provider is configured (OPENAI_API_KEY).');
        }
    }
}
