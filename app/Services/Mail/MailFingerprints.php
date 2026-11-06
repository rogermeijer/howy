<?php

namespace App\Services\Mail;

use App\Enums\FingerprintKind;
use App\Enums\FingerprintUse;
use App\Models\Email;
use App\Models\EmailFingerprint;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recognises text Howy has already read, however it comes back.
 *
 * A message returns quoted under a reply, forwarded, re-wrapped by another
 * mail client, with "> " before every line or its links rewritten. So text
 * is compared on its letters and digits only, per paragraph and as a whole.
 * Short paragraphs (greetings, "thanks", a name) say too little to recognise
 * anything by and are neither known nor new.
 */
class MailFingerprints
{
    /**
     * Only letters and digits, lower-cased: what survives every client.
     */
    public static function normalize(string $text): string
    {
        $text = (string) preg_replace('/^[ \t]*(?:>[ \t]?)+/mu', '', $text);
        $text = (string) preg_replace('/\[(?:cid|image)[^\]]*\]/iu', '', $text);
        $text = (string) preg_replace('~(?:https?://|www\.)\S+~iu', '', $text);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text));
    }

    public static function hash(string $text): string
    {
        return hash('sha256', self::normalize($text));
    }

    /**
     * The paragraphs that say enough to be recognised, keyed by their hash.
     *
     * @return array<string, string>
     */
    public function paragraphs(string $text): array
    {
        $text = (string) preg_replace('/^[ \t]*(?:>[ \t]?)+/mu', '', str_replace("\r\n", "\n", $text));
        $paragraphs = [];

        foreach (preg_split('/\n[ \t]*\n/u', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if (mb_strlen(self::normalize($paragraph)) >= (int) config('knowledge.mail.min_paragraph_chars')) {
                $paragraphs[self::hash($paragraph)] = $paragraph;
            }
        }

        return $paragraphs;
    }

    /**
     * Which of these hashes were read before, in a mail other than this one:
     * a mail interpreted again starts from a clean reading.
     *
     * @param  list<string>  $hashes
     * @return Collection<string, EmailFingerprint>
     */
    public function known(FingerprintKind $kind, array $hashes, ?Email $except = null): Collection
    {
        if ($hashes === []) {
            /** @var Collection<string, EmailFingerprint> */
            return new Collection;
        }

        return EmailFingerprint::query()
            ->where('kind', $kind)
            ->whereIn('hash', $hashes)
            ->when($except !== null, fn ($query) => $query->where('email_id', '!=', $except?->id))
            ->get()
            ->keyBy('hash');
    }

    /**
     * Remember text as read in this mail: the whole of it and each paragraph.
     * What was already known keeps where it was first read.
     */
    public function record(Email $email, string $text, FingerprintUse $use, ?int $documentVersionId = null): void
    {
        $normalized = self::normalize($text);

        if ($normalized === '') {
            return;
        }

        $this->remember($email, FingerprintKind::Message, hash('sha256', $normalized), mb_strlen($normalized), $use, $documentVersionId);

        foreach ($this->paragraphs($text) as $hash => $paragraph) {
            $this->remember($email, FingerprintKind::Paragraph, $hash, mb_strlen(self::normalize($paragraph)), $use, $documentVersionId);
        }
    }

    private function remember(Email $email, FingerprintKind $kind, string $hash, int $length, FingerprintUse $use, ?int $documentVersionId): void
    {
        EmailFingerprint::query()->createOrFirst(
            ['kind' => $kind, 'hash' => $hash],
            ['email_id' => $email->id, 'char_count' => $length, 'handled_as' => $use, 'document_version_id' => $documentVersionId],
        );
    }
}
