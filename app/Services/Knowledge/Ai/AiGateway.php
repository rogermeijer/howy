<?php

namespace App\Services\Knowledge\Ai;

use App\Models\AiCallPayload;
use App\Models\AiUsageRecord;
use App\Models\Email;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Files\File;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

/**
 * The one way out to an AI provider. Every call goes through here, so this is
 * where we decide what may leave the application and where every token is
 * accounted to the account and the step that spent it.
 *
 * What is sent: document text (chunks, sections, whole documents for context
 * lines), for scanned pages the pages themselves, and for mail addressed to a
 * mailbox its subject and new text, with any addresses in it masked. Never the
 * envelope (senders, recipients), account or user names, email addresses or ids. With OPENAI_STORE=false OpenAI keeps no copy
 * for later retrieval; OpenAI does not train on API data.
 *
 * What is kept here: every call, failed ones included, as an AiUsageRecord
 * (tokens, cost, latency, agent, thread) for good, and what was sent and
 * received as an encrypted AiCallPayload, pruned after
 * knowledge.ai_log.payload_retention_days. Super-admins read them at /admin/ai.
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
            $started = hrtime(true);

            try {
                $response = Embeddings::for($batch)
                    ->dimensions((int) config('knowledge.embeddings.dimensions'))
                    ->timeout(120)
                    ->generate($provider, $model);
            } catch (Throwable $e) {
                $this->record($step, $subject, $provider, $model, 0, 0, 0, details: new AiCallDetails(
                    agent: 'embeddings',
                    failed: true,
                    error: $e->getMessage(),
                    latencyMs: $this->since($started),
                    inputCount: count($batch),
                    input: $this->json($batch),
                ));

                throw $e;
            }

            $this->record($step, $subject, $provider, $model, $response->usage->inputTokens, 0, 0, details: new AiCallDetails(
                agent: 'embeddings',
                latencyMs: $this->since($started),
                inputCount: count($batch),
                input: $this->json($batch),
            ));

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
        $started = hrtime(true);

        try {
            /** @var AgentResponse $response */
            $response = $agent->prompt($prompt, $attachments, $provider, $model, timeout: 300);
        } catch (Throwable $e) {
            $this->record($step, $subject, $provider, $model, 0, 0, 0, details: new AiCallDetails(
                agent: class_basename($agent),
                failed: true,
                error: $e->getMessage(),
                latencyMs: $this->since($started),
                instructions: (string) $agent->instructions(),
                input: $prompt,
                attachments: $this->describe($attachments),
            ));

            throw $e;
        }

        $usage = $response->usage;
        $this->record(
            $step,
            $subject,
            $provider,
            $model,
            $usage->inputTokens,
            (int) $usage->cacheReadInputTokens,
            $usage->outputTokens,
            details: new AiCallDetails(
                agent: class_basename($agent),
                latencyMs: $this->since($started),
                instructions: (string) $agent->instructions(),
                input: $prompt,
                output: $response instanceof StructuredAgentResponse ? $this->json($response->structured) : $response->text,
                attachments: $this->describe($attachments),
            ),
        );

        return $response;
    }

    /**
     * Record a call, or usage of work done elsewhere, e.g. a batch whose
     * results came in.
     */
    public function record(
        string $step,
        ?Model $subject,
        string $provider,
        string $model,
        int $input,
        int $cached,
        int $output,
        bool $batch = false,
        ?AiCallDetails $details = null,
    ): AiUsageRecord {
        $record = AiUsageRecord::create([
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'thread_key' => $subject instanceof Email ? $subject->provider_thread_id : null,
            'step' => $step,
            'agent' => $details?->agent,
            'provider' => $provider,
            'model' => $model,
            'status' => $details?->failed ? AiUsageRecord::FAILED : AiUsageRecord::SUCCEEDED,
            'error' => $details?->error === null ? null : Str::limit($details->error, 2000),
            'latency_ms' => $details?->latencyMs,
            'input_count' => $details?->inputCount,
            'input_tokens' => $input,
            'cached_input_tokens' => $cached,
            'output_tokens' => $output,
            'is_batch' => $batch,
            'estimated_cost_micros' => $this->costMicros($model, $input, $cached, $output, $batch),
        ]);

        if ($details !== null && $details->hasPayload() && config('knowledge.ai_log.payloads')) {
            AiCallPayload::create([
                'ai_usage_record_id' => $record->id,
                'instructions' => $details->instructions,
                'input' => $details->input,
                'output' => $details->output,
                'attachments' => $details->attachments === [] ? null : $details->attachments,
            ]);
        }

        return $record;
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
        $price = $this->priceFor($model);

        if ($price === null) {
            return 0;
        }

        $cost = ($input - $cached) * $price['input'] + $cached * $price['cached_input'] + $output * $price['output'];

        return (int) round($batch ? $cost / 2 : $cost);
    }

    /**
     * The configured price for a model. Providers report dated snapshots
     * (gpt-6-luna-2026-08-01) where we configure the alias, so the longest
     * configured name the model starts with counts too.
     *
     * @return array{input: float, cached_input: float, output: float}|null
     */
    private function priceFor(string $model): ?array
    {
        /** @var array<string, array{input: float, cached_input: float, output: float}> $pricing */
        $pricing = (array) config('knowledge.pricing', []);

        if (isset($pricing[$model])) {
            return $pricing[$model];
        }

        $match = collect(array_keys($pricing))
            ->filter(fn (string $name): bool => str_starts_with($model, $name.'-'))
            ->sortByDesc(fn (string $name): int => strlen($name))
            ->first();

        return $match === null ? null : $pricing[$match];
    }

    private function since(int|float $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }

    private function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param  list<File>  $attachments
     * @return list<array{type: string, name: string|null, mime_type: string|null}>
     */
    private function describe(array $attachments): array
    {
        return array_map(fn (File $file): array => [
            'type' => class_basename($file),
            'name' => $file->name(),
            'mime_type' => $file->mimeType(),
        ], $attachments);
    }

    private function ensureEnabled(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('No AI provider is configured (OPENAI_API_KEY).');
        }
    }
}
