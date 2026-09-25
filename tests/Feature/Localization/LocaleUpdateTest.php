<?php

namespace Tests\Feature\Localization;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_switch_language(): void
    {
        $response = $this->from(route('home'))
            ->put(route('locale.update'), ['locale' => 'nl']);

        $response->assertRedirect(route('home'));
        // Plain, not encrypted: the cookie is in the encryptCookies except list.
        $response->assertPlainCookie(SetLocale::COOKIE, 'nl');
    }

    public function test_switching_persists_the_choice_for_a_signed_in_user(): void
    {
        $user = User::factory()->withAccount()->create()->refresh();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->put(route('locale.update'), ['locale' => 'nl'])
            ->assertRedirect(route('dashboard'));

        // Both the cookie and the column are written, so they cannot disagree.
        $this->assertSame(Locale::Dutch, $user->refresh()->locale);
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        $this->from(route('home'))
            ->put(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }

    public function test_the_locale_is_required(): void
    {
        $this->from(route('home'))
            ->put(route('locale.update'), [])
            ->assertSessionHasErrors('locale');
    }
}
