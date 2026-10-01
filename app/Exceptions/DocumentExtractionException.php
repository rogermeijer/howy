<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A document could not be read. The message is shown to the user as the
 * document's error, so it is translated and says what to do.
 */
class DocumentExtractionException extends RuntimeException
{
    public static function encrypted(): self
    {
        return new self(__('This PDF is protected. Save it without a password and upload it again.'));
    }

    public static function tooManyPages(int $pages, int $max): self
    {
        return new self(__('This document has :pages pages; the maximum is :max.', ['pages' => $pages, 'max' => $max]));
    }

    public static function unreadable(string $detail): self
    {
        return new self(__('The document could not be read: :detail', ['detail' => $detail]));
    }

    public static function empty(): self
    {
        return new self(__('No text was found in this document.'));
    }
}
