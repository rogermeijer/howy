<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->withVoucher()->post(route('register.store'), $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_an_account_name()
    {
        $response = $this->withVoucher()->post(route('register.store'), $this->payload(['account_name' => '']));

        $response->assertSessionHasErrors('account_name');
        $this->assertGuest();
    }

    public function test_registration_creates_an_account_and_makes_the_user_its_admin()
    {
        $this->withVoucher()->post(route('register.store'), $this->payload());

        $user = User::whereEmail('test@example.com')->sole();
        $account = Account::whereName('Test Company')->sole();

        $this->assertTrue($user->belongsToAccount($account));
        $this->assertTrue($user->isAdminOf($account));

        // The platform-wide flag is a different thing entirely and must stay false.
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_registration_sets_the_active_account()
    {
        $this->withVoucher()->post(route('register.store'), $this->payload());

        $user = User::whereEmail('test@example.com')->sole();

        $this->assertSame(Account::whereName('Test Company')->sole()->id, $user->active_account_id);
    }

    public function test_registration_cannot_mint_a_platform_administrator()
    {
        $this->withVoucher()->post(route('register.store'), $this->payload(['is_admin' => true]));

        $this->assertFalse(User::whereEmail('test@example.com')->sole()->is_admin);
    }

    /**
     * Registration needs a checked invite code in the session, as the code step leaves it.
     */
    private function withVoucher(?Voucher $voucher = null): static
    {
        $voucher ??= Voucher::factory()->create();

        return $this->withSession([Voucher::SESSION_KEY => $voucher->code]);
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
