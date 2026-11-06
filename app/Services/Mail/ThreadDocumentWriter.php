<?php

namespace App\Services\Mail;

use App\Enums\DocumentType;
use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Services\Knowledge\DocumentIntake;
use App\Services\Knowledge\IntakeSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Files history too long to read as a mail as a document of its own: one
 * section per message, oldest first, so it is chunked, checked against what
 * is known and reviewed like any document, and every answer it gives cites
 * the thread it came from.
 *
 * Only what Howy has not read goes in; a thread it was on from the start
 * has no history to file.
 *
 * @phpstan-import-type Quote from Email
 */
class ThreadDocumentWriter
{
    public function __construct(private readonly DocumentIntake $intake) {}

    /**
     * @param  list<Quote>  $unseen  newest first, as the mail quotes them
     * @param  bool  $withBody  the mail's own text is too long as well
     */
    public function write(Email $email, array $unseen, bool $withBody, ?string $language): ?DocumentVersion
    {
        $subject = trim((string) preg_replace('/^\s*(?:(?:re|aw|fwd?|fw|doorst|wg|tr|rv)\s*:\s*)+/iu', '', (string) $email->subject)) ?: (string) __('(no subject)');

        $messages = array_map(fn (array $quote): array => [$quote['name'] ?? $quote['email'] ?? (string) __('Unknown sender'), $quote['date'], $quote['text']], array_reverse($unseen));

        if ($withBody) {
            $messages[] = [$email->from_name ?? $email->from_email ?? (string) __('Unknown sender'), $email->sent_at?->toIso8601String(), (string) ($email->content_text ?? $email->body_text ?? '')];
        }

        $messages = array_values(array_filter($messages, fn (array $message): bool => trim($message[2]) !== ''));

        if ($messages === []) {
            return null;
        }

        $markdown = "# {$subject}\n\n".implode("\n\n", array_map(fn (array $message): string => $this->section(...$message), $messages))."\n";

        $base = (string) tempnam(sys_get_temp_dir(), 'cc-thread-');
        $file = $base.'.md';
        file_put_contents($file, $markdown);
        @unlink($base);

        try {
            return $this->intake->newDocument($file, Str::limit($subject, 200, '').'.md', IntakeSource::mail($email, (string) __('The history of this thread, filed as a document because it is too long to read as a mail.')), [
                'type' => DocumentType::Correspondence,
                'is_core' => false,
                'language' => Locale::tryFrom((string) $language) ?? Tenancy::account()->locale,
            ]);
        } finally {
            @unlink($file);
        }
    }

    /**
     * "## Anna de Vries — 29 Sep 2026" and what she wrote; a line that would
     * read as a heading is kept as text.
     */
    private function section(string $sender, ?string $date, string $text): string
    {
        $when = $date === null ? null : Carbon::parse($date)->timezone(Tenancy::account()->timezone)->isoFormat('D MMM YYYY');
        $heading = '## '.str_replace("\n", ' ', $sender).($when !== null ? " — {$when}" : '');

        return $heading."\n\n".preg_replace('/^(\s*)#/mu', '$1\\#', trim($text));
    }
}
