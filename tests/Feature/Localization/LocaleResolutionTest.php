<?php

namespace Tests\Feature\Localization;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_falls_back_to_the_application_locale(): void
    {
        $this->get(route('home'))->assertOk();

        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    public function test_a_guest_gets_the_locale_from_accept_language(): void
    {
        $this->withHeader('Accept-Language', 'nl-NL,nl;q=0.9')
            ->get(route('home'))
            ->assertOk();

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_an_unsupported_accept_language_falls_back_to_english(): void
    {
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->get(route('home'))
            ->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_cookie_wins_over_accept_language(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->withUnencryptedCookie(SetLocale::COOKIE, 'nl')
            ->get(route('home'))
            ->assertOk();

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_a_member_without_a_preference_follows_their_account(): void
    {
        $account = Account::factory()->create(['locale' => 'nl']);
        $user = User::factory()->withAccount($account)->create()->refresh();

        $this->assertNull($user->locale);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_a_members_own_preference_wins_over_their_account(): void
    {
        $account = Account::factory()->create(['locale' => 'nl']);
        $user = User::factory()->withAccount($account)->create();
        $user->forceFill(['locale' => Locale::English])->save();

        $this->actingAs($user->refresh())->get(route('dashboard'))->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_a_members_locale_beats_the_cookie(): void
    {
        $account = Account::factory()->create(['locale' => 'nl']);
        $user = User::factory()->withAccount($account)->create()->refresh();

        // A stale cookie from before signing in must not override the account.
        $this->actingAs($user)
            ->withUnencryptedCookie(SetLocale::COOKIE, 'en')
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_the_resolved_timezone_follows_the_same_chain(): void
    {
        $account = Account::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $user = User::factory()->withAccount($account)->create()->refresh();

        $this->assertSame('Europe/Amsterdam', $user->resolvedTimezone());

        $user->forceFill(['timezone' => 'Asia/Tokyo'])->save();

        $this->assertSame('Asia/Tokyo', $user->refresh()->resolvedTimezone());
    }
}
