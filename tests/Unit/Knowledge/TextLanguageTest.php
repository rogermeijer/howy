<?php

namespace Tests\Unit\Knowledge;

use App\Enums\Locale;
use App\Services\Knowledge\Extraction\TextLanguage;
use PHPUnit\Framework\TestCase;

class TextLanguageTest extends TestCase
{
    private const string ENGLISH = 'All employees are entitled to twenty-five days of leave per year. If you are sick, report it to your manager before nine in the morning. The company will pay your travel costs when you work at the office. Our values guide the decisions we make, and they apply to all of us. You can ask your manager for help with this, or contact the people team at any time. Leave that is not taken by the end of the year will be carried over for six months.';

    private const string DUTCH = 'Alle medewerkers hebben recht op vijfentwintig vakantiedagen per jaar. Als je ziek bent, meld je dat voor negen uur bij je leidinggevende. De organisatie vergoedt je reiskosten als je op kantoor werkt. Onze waarden zijn leidend bij de keuzes die we maken, en ze gelden voor ons allemaal. Je kunt je leidinggevende om hulp vragen, of op elk moment contact opnemen met het team. Verlof dat niet voor het einde van het jaar is opgenomen, wordt zes maanden bewaard.';

    public function test_it_tells_english_from_dutch(): void
    {
        $this->assertSame(Locale::English, TextLanguage::guess(self::ENGLISH));
        $this->assertSame(Locale::Dutch, TextLanguage::guess(self::DUTCH));
    }

    public function test_too_little_text_is_not_guessed(): void
    {
        $this->assertNull(TextLanguage::guess('Personnel Handbook 2027'));
        $this->assertNull(TextLanguage::guess(''));
    }

    public function test_a_mix_is_not_guessed(): void
    {
        $this->assertNull(TextLanguage::guess(self::ENGLISH.' '.self::DUTCH));
    }
}
