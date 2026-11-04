<?php

namespace App\Services\Mail;

/**
 * The attachments of one mail that wait for a decision, downloaded to temp
 * files for the length of one reading, by their index in the mail.
 */
final class PreparedAttachments
{
    /**
     * @param  array<int, string>  $files  index in the mail's attachments => temp file
     */
    public function __construct(private array $files = []) {}

    public function hasNew(): bool
    {
        return $this->files !== [];
    }

    public function has(int $index): bool
    {
        return isset($this->files[$index]);
    }

    public function path(int $index): ?string
    {
        return $this->files[$index] ?? null;
    }

    /**
     * @return list<int>
     */
    public function indexes(): array
    {
        return array_keys($this->files);
    }

    public function cleanup(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        $this->files = [];
    }
}
