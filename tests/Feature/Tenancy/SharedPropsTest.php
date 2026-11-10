<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_current_account_and_the_number_of_memberships_reach_the_frontend(): void
    {
        $first = Account::factory()->create(['name' => 'Acme', 'locale' => 'nl']);
        $second = Account::factory()->create(['name' => 'Globex']);

        $user = User::factory()->withAccount($first)->create()->refresh();
        $second->users()->attach($user, ['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.account.name', 'Acme')
                // The list itself is searched by the switcher, never shared.
                ->where('auth.accountCount', 2)
                ->missing('auth.accounts')
                ->where('locale', 'nl')
                ->where('timezone', 'UTC')
                ->has('translations')
            );
    }

    public function test_a_guest_gets_no_account_and_no_memberships(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.account', null)
                ->where('auth.accountCount', 0)
            );
    }
}
