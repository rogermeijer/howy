<?php

namespace App\Mail\Content;

/**
 * A mail ready to go out: the subject, the designed HTML and the same
 * message as plain text for clients that show no HTML.
 */
final readonly class RenderedMail
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $text,
    ) {}
}
