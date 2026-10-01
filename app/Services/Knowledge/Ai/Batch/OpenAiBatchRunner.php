<?php

namespace App\Services\Knowledge\Ai\Batch;

use App\Services\Knowledge\Ai\AiGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\ObjectSchema;
use RuntimeException;

/**
 * The OpenAI Batch API over the Responses endpoint: upload a JSONL file of
 * requests, create a batch, poll it, download the results. Half the price of
 * the same requests in real time, finished within 24 hours (usually minutes).
 *
 * Request bodies mirror what laravel/ai sends for the same agent in real time:
 * instructions, the prompt, the JSON schema and the agent's provider options.
 */
class OpenAiBatchRunner implements BatchRunner
{
    public function __construct(private readonly AiGateway $ai) {}

    public function submit(array $requests): string
    {
        if ($requests === []) {
            throw new RuntimeException('A batch needs at least one request.');
        }

        $lines = array_map(fn (BatchRequest $request): string => json_encode([
            'custom_id' => $request->customId,
            'method' => 'POST',
            'url' => '/v1/responses',
            'body' => $this->body($request),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $requests);

        $file = $this->http()
            ->attach('file', implode("\n", $lines)."\n", 'requests.jsonl')
            ->post('files', ['purpose' => 'batch'])
            ->throw()
            ->json('id');

        return (string) $this->http()->post('batches', [
            'input_file_id' => $file,
            'endpoint' => '/v1/responses',
            'completion_window' => '24h',
        ])->throw()->json('id');
    }

    public function status(string $batchId): BatchStatus
    {
        /** @var array{status: string, output_file_id?: string|null, error_file_id?: string|null, errors?: array{data?: list<array{message?: string}>}|null} $batch */
        $batch = $this->http()->get("batches/{$batchId}")->throw()->json();

        return match ($batch['status']) {
            'completed' => new BatchStatus(BatchStatus::COMPLETED, [
                ...$this->results($batch['output_file_id'] ?? null),
                ...$this->results($batch['error_file_id'] ?? null),
            ]),
            'failed', 'expired', 'cancelled' => new BatchStatus(
                BatchStatus::FAILED,
                error: $batch['errors']['data'][0]['message'] ?? "Batch {$batch['status']}.",
            ),
            default => new BatchStatus(BatchStatus::PENDING),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function body(BatchRequest $request): array
    {
        $agent = $request->agent;

        $body = [
            'model' => $this->ai->modelFor($request->step),
            'instructions' => (string) $agent->instructions(),
            'input' => [['role' => 'user', 'content' => $request->prompt]],
            'store' => false,
        ];

        if ($agent instanceof HasStructuredOutput) {
            $strict = Strict::isAppliedTo($agent);
            $schema = (new ObjectSchema($agent->schema(new JsonSchemaTypeFactory), strict: $strict))->toSchema();

            $body['text'] = ['format' => [
                'type' => 'json_schema',
                'name' => $schema['name'] ?? 'schema_definition',
                'schema' => Arr::except($schema, ['name']),
                'strict' => $strict,
            ]];
        }

        if ($agent instanceof HasProviderOptions) {
            $body = [...$body, ...$agent->providerOptions(Lab::OpenAI)];
        }

        return $body;
    }

    /**
     * @return list<BatchResult>
     */
    private function results(?string $fileId): array
    {
        if ($fileId === null || $fileId === '') {
            return [];
        }

        $jsonl = $this->http()->get("files/{$fileId}/content")->throw()->body();
        $results = [];

        foreach (preg_split('/\R/', trim($jsonl)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            /** @var array{custom_id: string, response?: array{status_code?: int, body?: array<string, mixed>}|null, error?: array{message?: string}|null} $row */
            $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $body = $row['response']['body'] ?? [];

            if (($row['response']['status_code'] ?? 0) !== 200) {
                $results[] = new BatchResult($row['custom_id'], null, (string) ($body['model'] ?? ''), error: $row['error']['message'] ?? 'Request failed.');

                continue;
            }

            $results[] = new BatchResult(
                $row['custom_id'],
                $this->structuredOutput($body),
                (string) ($body['model'] ?? ''),
                (int) data_get($body, 'usage.input_tokens', 0),
                (int) data_get($body, 'usage.input_tokens_details.cached_tokens', 0),
                (int) data_get($body, 'usage.output_tokens', 0),
            );
        }

        return $results;
    }

    /**
     * The JSON text of the response's message, decoded.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function structuredOutput(array $body): ?array
    {
        foreach ((array) ($body['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $decoded = json_decode($content['text'], true);

                    return is_array($decoded) ? $decoded : null;
                }
            }
        }

        return null;
    }

    private function http(): PendingRequest
    {
        $key = config('ai.providers.openai.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('No OpenAI key configured.');
        }

        return Http::withToken($key)
            ->baseUrl(rtrim((string) config('ai.providers.openai.url'), '/'))
            ->acceptJson()
            ->timeout(120);
    }
}
