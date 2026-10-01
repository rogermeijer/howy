<?php

namespace App\Services\Mail\Interpretation;

/**
 * A reply as it goes out: the designed HTML, the images it embeds, and the
 * same message as plain text for clients that show no HTML. The text is also
 * what the thread page shows as the reply.
 */
final readonly class ComposedReply
{
    /**
     * @param  array<string, string>  $inline  images the HTML shows as `cid:<name>`, by name → file path
     */
    public function __construct(
        public string $text,
        public string $html,
        public array $inline = [],
    ) {}
}
