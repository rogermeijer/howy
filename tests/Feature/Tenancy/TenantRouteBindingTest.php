<?php

namespace Tests\Feature\Tenancy;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\TenantFixture;
use Tests\TestCase;

/**
 * Route-model binding inherits the global scope for free, because
 * resolveRouteBinding() builds from newQuery(). This proves it, since an
 * accidental override or a hand-written Route::bind() would silently undo it.
 */
class TenantRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TenantFixture::migrate();

        Route::middleware(['web', 'auth', 'tenant'])
            ->get('__test/fixtures/{tenantFixture}', fn (TenantFixture $tenantFixture) => response()->json([
                'id' => $tenantFixture->id,
            ]));
    }

    public function test_a_record_from_the_current_account_resolves(): void
    {
        [$user, $account] = $this->memberOfNewAccount();

        $fixture = Tenancy::for($account, fn () => TenantFixture::create(['name' => 'Mine']));

        $this->actingAs($user)
            ->get("__test/fixtures/{$fixture->id}")
            ->assertOk()
            ->assertJson(['id' => $fixture->id]);
    }

    public function test_a_record_from_another_account_returns_404(): void
    {
        [$user] = $this->memberOfNewAccount();

        $others = Account::factory()->create();
        $theirs = Tenancy::for($others, fn () => TenantFixture::create(['name' => 'Theirs']));

        $this->actingAs($user)
            ->get("__test/fixtures/{$theirs->id}")
            ->assertNotFound();
    }

    /**
     * @return array{User, Account}
     */
    private function memberOfNewAccount(): array
    {
        $account = Account::factory()->create();
        $user = User::factory()->withAccount($account)->create();

        return [$user->refresh(), $account];
    }
}
