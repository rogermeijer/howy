<?php

namespace App\Services\Mail\Interpretation;

/**
 * A reply as it goes out: the designed HTML, and the same message as plain
 * text for clients that show no HTML. The text is also what the thread page
 * shows as the reply. The HTML embeds nothing, so a reply carries no
 * attachment.
 */
final readonly class ComposedReply
{
    public function __construct(
        public string $text,
        public string $html,
    ) {}
}
