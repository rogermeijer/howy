<?php

namespace App\Services\Knowledge\Extraction;

/**
 * What an image holds, as read by the vision model.
 */
final readonly class ImageContent
{
    public function __construct(
        public string $kind,
        public bool $worthKeeping,
        public string $title,
        public string $description,
        public string $markdown,
    ) {}

    /**
     * The image as a document of its own: its title, what it shows, and the
     * text on it.
     *
     * @return list<Block>
     */
    public function blocks(): array
    {
        $markdown = implode("\n\n", array_filter([
            $this->title !== '' ? "# {$this->title}" : null,
            trim($this->description),
            trim($this->markdown),
        ], fn (?string $part): bool => $part !== null && $part !== ''));

        return MarkdownBlocks::parse($markdown, 1);
    }
}
