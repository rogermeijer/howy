<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentAccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_switch_to_another_of_their_accounts(): void
    {
        $first = Account::factory()->create();
        $second = Account::factory()->create();

        $user = User::factory()->withAccount($first)->create()->refresh();
        $second->users()->attach($user, ['is_admin' => false]);

        $this->actingAs($user)
            ->put(route('current-account.update'), ['account_id' => $second->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($second->id, $user->refresh()->active_account_id);
    }

    public function test_a_non_member_cannot_switch_to_an_account(): void
    {
        $mine = Account::factory()->create();
        $theirs = Account::factory()->create();

        $user = User::factory()->withAccount($mine)->create()->refresh();

        $this->actingAs($user)
            ->put(route('current-account.update'), ['account_id' => $theirs->id])
            ->assertSessionHasErrors('account_id');

        $this->assertSame($mine->id, $user->refresh()->active_account_id);
    }

    public function test_switching_to_a_nonexistent_account_fails_validation(): void
    {
        $user = User::factory()->withAccount()->create()->refresh();

        $this->actingAs($user)
            ->put(route('current-account.update'), ['account_id' => 9999])
            ->assertSessionHasErrors('account_id');
    }

    public function test_switching_requires_authentication(): void
    {
        $account = Account::factory()->create();

        $this->put(route('current-account.update'), ['account_id' => $account->id])
            ->assertRedirect(route('login'));
    }
}
