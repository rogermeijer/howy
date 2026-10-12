<?php

namespace Tests\Feature\Auth;

use App\Models\BetaAccessRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_the_registration_screen_asks_for_a_code_first(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->where('code', null));
    }

    public function test_a_valid_code_unlocks_the_registration_form(): void
    {
        BetaAccessRequest::factory()->create()->forceFill(['code' => 'HOWY-ABCD-EFGH'])->save();

        $this->post(route('register.code.store'), ['code' => ' howy-abcd-efgh '])
            ->assertRedirect(route('register'))
            ->assertSessionHas(BetaAccessRequest::SESSION_KEY, 'HOWY-ABCD-EFGH');

        $this->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page->where('code', 'HOWY-ABCD-EFGH'));
    }

    public function test_the_invite_link_fills_in_the_code(): void
    {
        $invite = BetaAccessRequest::factory()->invited()->create();

        $this->get(route('register.invite', $invite->code))
            ->assertRedirect(route('register'))
            ->assertSessionHas(BetaAccessRequest::SESSION_KEY, $invite->code);
    }

    public function test_a_dead_invite_link_lands_on_the_code_step(): void
    {
        $invite = BetaAccessRequest::factory()->redeemed()->create();

        $this->get(route('register.invite', $invite->code))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('code')
            ->assertSessionMissing(BetaAccessRequest::SESSION_KEY);
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $this->from(route('register'))
            ->post(route('register.code.store'), ['code' => 'HOWY-NOPE-NOPE'])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('code')
            ->assertSessionMissing(BetaAccessRequest::SESSION_KEY);
    }

    public function test_a_redeemed_code_is_rejected(): void
    {
        $invite = BetaAccessRequest::factory()->redeemed()->create();

        $this->post(route('register.code.store'), ['code' => $invite->code])
            ->assertSessionHasErrors('code');
    }

    public function test_the_code_can_be_changed(): void
    {
        $invite = BetaAccessRequest::factory()->create();

        $this->withSession([BetaAccessRequest::SESSION_KEY => $invite->code])
            ->delete(route('register.code.destroy'))
            ->assertRedirect(route('register'))
            ->assertSessionMissing(BetaAccessRequest::SESSION_KEY);
    }

    public function test_registering_without_a_code_is_refused(): void
    {
        $this->post(route('register.store'), $this->payload())
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registering_redeems_the_code(): void
    {
        $invite = BetaAccessRequest::factory()->invited()->create();

        $this->withSession([BetaAccessRequest::SESSION_KEY => $invite->code])
            ->post(route('register.store'), $this->payload())
            ->assertSessionMissing(BetaAccessRequest::SESSION_KEY);

        $this->assertAuthenticated();

        $invite->refresh();
        $this->assertNotNull($invite->redeemed_at);
        $this->assertSame(User::whereEmail('test@example.com')->sole()->id, $invite->user_id);
        $this->assertSame('registered', $invite->status());
    }

    public function test_a_code_redeemed_between_the_steps_is_refused(): void
    {
        $invite = BetaAccessRequest::factory()->create();

        // Someone else registered with it after this visitor's code step.
        $invite->forceFill(['redeemed_at' => now()])->save();

        $this->withSession([BetaAccessRequest::SESSION_KEY => $invite->code])
            ->post(route('register.store'), $this->payload())
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertNull($invite->refresh()->user_id);
    }

    public function test_a_code_lets_in_one_registration_only(): void
    {
        $invite = BetaAccessRequest::factory()->create();

        $this->withSession([BetaAccessRequest::SESSION_KEY => $invite->code])
            ->post(route('register.store'), $this->payload());

        $this->post(route('logout'));

        $this->withSession([BetaAccessRequest::SESSION_KEY => $invite->code])
            ->post(route('register.store'), $this->payload(['email' => 'second@example.com']))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Test User',
            'account_name' => 'Test Company',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }
}
