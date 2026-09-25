<?php

namespace Tests\Feature\Localization;

use App\Enums\Locale;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Guards the sweep: the interface must not drift back to hardcoded Dutch.
 */
class TranslationCatalogueTest extends TestCase
{
    /**
     * Dutch words common enough that finding one outside a translation call means
     * a string was hardcoded rather than translated.
     *
     * @var list<string>
     */
    private const array DUTCH_MARKERS = [
        'Wachtwoord', 'Inloggen', 'Uitloggen', 'Instellingen', 'Kennisbank',
        'Aanmelden', 'Opslaan', 'Bevestigen', 'Gegevens', 'Afzender',
        'Zekerheid', 'Annuleren', 'Verwijderen',
    ];

    public function test_every_locale_has_a_loadable_catalogue(): void
    {
        foreach (Locale::cases() as $locale) {
            $catalogue = Lang::getLoader()->load($locale->value, '*', '*');

            $this->assertIsArray($catalogue);

            // English needs no catalogue: the keys are the English strings.
            if ($locale !== Locale::English) {
                $this->assertNotEmpty($catalogue, "Locale [{$locale->value}] has no translations.");
            }
        }
    }

    public function test_the_dutch_catalogue_translates_a_known_application_string(): void
    {
        app()->setLocale('nl');

        $this->assertSame('Uitloggen', __('Log out'));
        $this->assertSame('Wisselen van account', __('Switch account'));

        app()->setLocale('en');

        // With no English catalogue the key is returned unchanged, which is the
        // English string — so a missing translation still renders correctly.
        $this->assertSame('Log out', __('Log out'));
    }

    public function test_every_translation_key_used_in_the_code_has_a_dutch_translation(): void
    {
        $catalogue = Lang::getLoader()->load('nl', '*', '*');
        $missing = [];

        foreach ([...$this->sourceFiles(), ...$this->phpFiles()] as $file) {
            $contents = $this->withoutComments(file_get_contents($file));

            // t('…') in TSX, __('…') in PHP.
            preg_match_all("/(?:\\bt|__)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $contents, $matches);

            foreach ($matches[1] as $key) {
                $key = str_replace("\\'", "'", $key);

                if (! array_key_exists($key, $catalogue)) {
                    $missing[$key] = basename($file);
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These keys render untranslated in Dutch. Add them to lang/nl.json.',
        );
    }

    public function test_no_page_or_component_hardcodes_dutch_interface_copy(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            foreach (file($file) as $number => $line) {
                // Demo fixture data is written as object literals
                // (`{ label: '...' }`) and is sample content, not interface copy.
                // This is a heuristic: it trades a blind spot inside data objects
                // for catching every string in JSX text and attributes, which is
                // where interface copy actually lives.
                if (preg_match('/^\s*\{?\s*[\w\'"]+\s*:\s/u', $line)) {
                    continue;
                }

                if (str_contains($line, 't(')) {
                    continue;
                }

                foreach (self::DUTCH_MARKERS as $marker) {
                    if (str_contains($line, $marker)) {
                        $offenders[] = basename($file).':'.($number + 1).' → '.$marker;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Interface copy must go through t(). Add the English source string and put the Dutch in lang/nl.json.',
        );
    }

    /**
     * Strip comments so documentation examples are not mistaken for real usage.
     */
    private function withoutComments(string $contents): string
    {
        return (string) preg_replace(['#/\\*.*?\\*/#s', '#^\\s*//.*$#m'], '', $contents);
    }

    /**
     * @return list<string>
     */
    private function phpFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['pages', 'components', 'layouts'] as $dir) {
            $base = resource_path('js/'.$dir);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'tsx') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
