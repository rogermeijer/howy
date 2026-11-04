<?php

namespace Tests\Unit;

use App\Services\Mail\MailFingerprints;
use PHPUnit\Framework\TestCase;

/**
 * Text comes back changed by every mail client it passes through; what Howy
 * read must still be recognised.
 */
class MailFingerprintsTest extends TestCase
{
    private const string ORIGINAL = "Vanaf 1 januari hebben medewerkers recht op 26 vakantiedagen per jaar.\nDe extra dag geldt ook voor parttimers.";

    public function test_rewrapped_text_hashes_the_same(): void
    {
        $rewrapped = "Vanaf 1 januari hebben medewerkers recht op 26\nvakantiedagen per jaar. De extra dag geldt\nook voor parttimers.";

        $this->assertSame(MailFingerprints::hash(self::ORIGINAL), MailFingerprints::hash($rewrapped));
    }

    public function test_quote_markers_are_ignored(): void
    {
        $quoted = "> Vanaf 1 januari hebben medewerkers recht op 26 vakantiedagen per jaar.\n>> De extra dag geldt ook voor parttimers.";

        $this->assertSame(MailFingerprints::hash(self::ORIGINAL), MailFingerprints::hash($quoted));
    }

    public function test_rewritten_links_and_image_placeholders_are_ignored(): void
    {
        $original = 'Het nieuwe beleid staat op https://intranet.noordkade.nl/beleid/verlof en geldt per direct.';
        $rewritten = 'Het nieuwe beleid staat op https://eur01.safelinks.protection.outlook.com/?url=https%3A%2F%2Fintranet [cid:image001.png@01DA] en geldt per direct.';

        $this->assertSame(MailFingerprints::hash($original), MailFingerprints::hash($rewritten));
    }

    public function test_different_content_hashes_differently(): void
    {
        $this->assertNotSame(
            MailFingerprints::hash(self::ORIGINAL),
            MailFingerprints::hash(str_replace('26', '27', self::ORIGINAL)),
        );
    }
}
