<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * The kinds of file the knowledge base can read, uploaded or sent by mail.
 *
 * PDF goes through poppler, the office and text formats through pandoc (3.x
 * reads every one of them), plain text needs no tool and images are read by
 * a vision model. Old binary Office files (.doc, .xls, .ppt) would need
 * LibreOffice and are not accepted.
 */
enum DocumentFormat: string
{
    case Pdf = 'pdf';
    case Docx = 'docx';
    case Pptx = 'pptx';
    case Xlsx = 'xlsx';
    case Odt = 'odt';
    case Rtf = 'rtf';
    case Csv = 'csv';
    case Tsv = 'tsv';
    case Html = 'html';
    case Markdown = 'md';
    case Text = 'txt';
    case Png = 'png';
    case Jpeg = 'jpg';
    case Webp = 'webp';
    case Gif = 'gif';

    /**
     * The mime type a version of this format is stored with.
     */
    public function mimeType(): string
    {
        return $this->mimeTypes()[0];
    }

    /**
     * Every name the format goes by: what mail clients send and what file
     * inspection reports. Text formats are often reported as plain text.
     *
     * @return non-empty-list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Pdf => ['application/pdf'],
            self::Docx => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            self::Pptx => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            self::Xlsx => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            self::Odt => ['application/vnd.oasis.opendocument.text'],
            self::Rtf => ['application/rtf', 'text/rtf'],
            self::Csv => ['text/csv', 'application/csv', 'text/plain'],
            self::Tsv => ['text/tab-separated-values', 'text/plain'],
            self::Html => ['text/html'],
            self::Markdown => ['text/markdown', 'text/x-markdown', 'text/plain'],
            self::Text => ['text/plain'],
            self::Png => ['image/png'],
            self::Jpeg => ['image/jpeg'],
            self::Webp => ['image/webp'],
            self::Gif => ['image/gif'],
        };
    }

    /**
     * @return non-empty-list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Html => ['html', 'htm'],
            self::Markdown => ['md', 'markdown'],
            self::Jpeg => ['jpg', 'jpeg'],
            default => [$this->value],
        };
    }

    public function extension(): string
    {
        return $this->extensions()[0];
    }

    /**
     * The pandoc reader for this format; null for what pandoc does not read.
     */
    public function pandocReader(): ?string
    {
        return match ($this) {
            self::Docx, self::Pptx, self::Xlsx, self::Odt, self::Rtf, self::Csv, self::Tsv, self::Html => $this->value,
            self::Markdown => 'markdown',
            default => null,
        };
    }

    public function isImage(): bool
    {
        return in_array($this, [self::Png, self::Jpeg, self::Webp, self::Gif], true);
    }

    /**
     * Shown in the browser rather than downloaded.
     */
    public function opensInline(): bool
    {
        return $this === self::Pdf || $this->isImage();
    }

    /**
     * By file name first (what the sender called it), else by mime type.
     */
    public static function detect(string $filename, ?string $mimeType = null): ?self
    {
        $extension = Str::lower(pathinfo($filename, PATHINFO_EXTENSION));

        foreach (self::cases() as $format) {
            if (in_array($extension, $format->extensions(), true)) {
                return $format;
            }
        }

        $mimeType = Str::lower(trim(Str::before((string) $mimeType, ';')));

        foreach (self::cases() as $format) {
            if ($format->mimeType() === $mimeType) {
                return $format;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function allExtensions(): array
    {
        return array_merge(...array_map(fn (self $format): array => $format->extensions(), self::cases()));
    }

    /**
     * @return list<string>
     */
    public static function allMimeTypes(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (self $format): array => $format->mimeTypes(), self::cases()))));
    }
}
