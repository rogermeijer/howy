<?php

namespace App\Services\Knowledge\Ai;

/**
 * What the AiGateway knows about a call beyond its tokens: who made it, how it
 * went, and what was sent and received. The texts become an AiCallPayload when
 * payload logging is on.
 */
final readonly class AiCallDetails
{
    /**
     * @param  list<array{type: string, name: string|null, mime_type: string|null}>  $attachments
     */
    public function __construct(
        public ?string $agent = null,
        public bool $failed = false,
        public ?string $error = null,
        public ?int $latencyMs = null,
        public ?int $inputCount = null,
        public ?string $instructions = null,
        public ?string $input = null,
        public ?string $output = null,
        public array $attachments = [],
    ) {}

    public function hasPayload(): bool
    {
        return $this->instructions !== null || $this->input !== null || $this->output !== null || $this->attachments !== [];
    }
}
