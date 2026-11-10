<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const array PAGES = [
        'resources/js/pages/legal/privacy-policy.tsx',
        'resources/js/pages/legal/terms-of-service.tsx',
    ];

    public function test_a_guest_can_read_the_privacy_policy(): void
    {
        $this->get(route('privacy-policy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/privacy-policy'));
    }

    public function test_a_guest_can_read_the_terms_of_service(): void
    {
        $this->get(route('terms-of-service'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('legal/terms-of-service'));
    }

    public function test_the_legal_pages_live_on_their_own_paths(): void
    {
        $this->assertSame(url('privacy-policy'), route('privacy-policy'));
        $this->assertSame(url('terms-of-service'), route('terms-of-service'));
    }

    /**
     * The legal text is kept as English constants rather than t('…') calls, so
     * the catalogue test does not see it. Changing an English sentence without
     * its Dutch would quietly show English to Dutch readers.
     */
    public function test_every_sentence_of_the_legal_pages_has_a_dutch_translation(): void
    {
        $catalogue = Lang::getLoader()->load('nl', '*', '*');
        $missing = [];
        $checked = 0;

        foreach (self::PAGES as $page) {
            $contents = (string) file_get_contents(base_path($page));

            // Section titles, paragraphs and list items, and the page's title and intro.
            preg_match_all('/^\s*(?:title: )?([\'"])(.+)\1,$/m', $contents, $strings);
            preg_match_all('/^\s*(?:title|intro)="(.+)"$/m', $contents, $props);

            foreach ([...$strings[2], ...$props[1]] as $key) {
                $checked++;

                if (! array_key_exists($key, $catalogue)) {
                    $missing[$key] = basename($page);
                }
            }
        }

        // A pattern that stops matching would pass this test vacuously.
        $this->assertGreaterThan(100, $checked);
        $this->assertSame([], $missing, 'Add these legal sentences to lang/nl.json.');
    }
}
