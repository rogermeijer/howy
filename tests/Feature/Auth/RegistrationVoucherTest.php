<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationVoucherTest extends TestCase
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
                ->where('voucher', null));
    }

    public function test_a_valid_code_unlocks_the_registration_form(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'HOWY-ABCD-EFGH']);

        $this->post(route('register.voucher.store'), ['code' => ' howy-abcd-efgh '])
            ->assertRedirect(route('register'))
            ->assertSessionHas(Voucher::SESSION_KEY, $voucher->code);

        $this->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page->where('voucher', 'HOWY-ABCD-EFGH'));
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $this->from(route('register'))
            ->post(route('register.voucher.store'), ['code' => 'HOWY-NOPE-NOPE'])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('code')
            ->assertSessionMissing(Voucher::SESSION_KEY);
    }

    public function test_a_used_up_code_is_rejected(): void
    {
        $voucher = Voucher::factory()->usedUp()->create();

        $this->post(route('register.voucher.store'), ['code' => $voucher->code])
            ->assertSessionHasErrors('code');
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $voucher = Voucher::factory()->expired()->create();

        $this->post(route('register.voucher.store'), ['code' => $voucher->code])
            ->assertSessionHasErrors('code');
    }

    public function test_the_code_can_be_changed(): void
    {
        $voucher = Voucher::factory()->create();

        $this->withSession([Voucher::SESSION_KEY => $voucher->code])
            ->delete(route('register.voucher.destroy'))
            ->assertRedirect(route('register'))
            ->assertSessionMissing(Voucher::SESSION_KEY);
    }

    public function test_registering_without_a_code_is_refused(): void
    {
        $this->post(route('register.store'), $this->payload())
            ->assertSessionHasErrors('voucher');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registering_redeems_the_code(): void
    {
        $voucher = Voucher::factory()->create(['max_uses' => 2]);

        $this->withSession([Voucher::SESSION_KEY => $voucher->code])
            ->post(route('register.store'), $this->payload())
            ->assertSessionMissing(Voucher::SESSION_KEY);

        $this->assertAuthenticated();
        $this->assertSame(1, $voucher->refresh()->uses);
        $this->assertSame($voucher->id, User::whereEmail('test@example.com')->sole()->voucher_id);
    }

    public function test_a_code_that_ran_out_between_the_steps_is_refused(): void
    {
        $voucher = Voucher::factory()->create();

        // Someone else used the last registration after this visitor's code step.
        $voucher->increment('uses');

        $this->withSession([Voucher::SESSION_KEY => $voucher->code])
            ->post(route('register.store'), $this->payload())
            ->assertSessionHasErrors('voucher');

        $this->assertGuest();
        $this->assertSame(1, $voucher->refresh()->uses);
    }

    public function test_a_single_use_code_lets_in_one_registration_only(): void
    {
        $voucher = Voucher::factory()->create();

        $this->withSession([Voucher::SESSION_KEY => $voucher->code])
            ->post(route('register.store'), $this->payload());

        $this->post(route('logout'));

        $this->withSession([Voucher::SESSION_KEY => $voucher->code])
            ->post(route('register.store'), $this->payload(['email' => 'second@example.com']))
            ->assertSessionHasErrors('voucher');

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_the_voucher_command_mints_codes(): void
    {
        $this->artisan('vouchers:create', ['--count' => 3, '--uses' => 5, '--note' => 'Launch list'])
            ->assertSuccessful();

        $this->assertSame(3, Voucher::query()->where('max_uses', 5)->where('note', 'Launch list')->count());
        $this->assertTrue(Voucher::query()->get()->every(
            fn (Voucher $voucher): bool => (bool) preg_match('/^HOWY-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $voucher->code),
        ));
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
