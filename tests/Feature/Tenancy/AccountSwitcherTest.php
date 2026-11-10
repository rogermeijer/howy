<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSwitcherTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user whose current account is "Current", and who belongs to the named
     * accounts besides.
     *
     * @param  list<string>  $others
     */
    private function memberOf(array $others): User
    {
        $user = User::factory()
            ->withAccount(Account::factory()->create(['name' => 'Current']))
            ->create()
            ->refresh();

        foreach ($others as $name) {
            Account::factory()->create(['name' => $name])->users()->attach($user, ['is_admin' => false]);
        }

        return $user;
    }

    public function test_it_lists_the_other_accounts_by_name_without_the_current_one(): void
    {
        $user = $this->memberOf(['Globex', 'Beta']);

        $this->actingAs($user)
            ->getJson(route('account-switcher'))
            ->assertOk()
            ->assertExactJson([
                'accounts' => [
                    ['id' => Account::query()->where('name', 'Beta')->value('id'), 'name' => 'Beta'],
                    ['id' => Account::query()->where('name', 'Globex')->value('id'), 'name' => 'Globex'],
                ],
                'total' => 2,
            ]);
    }

    public function test_it_shows_five_and_counts_the_rest(): void
    {
        $user = $this->memberOf(['A', 'B', 'C', 'D', 'E', 'F', 'G']);

        $this->actingAs($user)
            ->getJson(route('account-switcher'))
            ->assertOk()
            ->assertJsonCount(5, 'accounts')
            ->assertJsonPath('accounts.4.name', 'E')
            ->assertJsonPath('total', 7);
    }

    public function test_search_ignores_case_and_puts_names_starting_with_it_first(): void
    {
        $user = $this->memberOf(['Acme Dev', 'DevKids', 'Bakkerij']);

        $response = $this->actingAs($user)
            ->getJson(route('account-switcher', ['q' => ' dev ']))
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->assertSame(['DevKids', 'Acme Dev'], array_column($response->json('accounts'), 'name'));
    }

    public function test_search_reaches_accounts_beyond_the_first_five(): void
    {
        $user = $this->memberOf(['A', 'B', 'C', 'D', 'E', 'Zeta']);

        $this->actingAs($user)
            ->getJson(route('account-switcher', ['q' => 'zet']))
            ->assertOk()
            ->assertJsonPath('accounts.0.name', 'Zeta')
            ->assertJsonPath('total', 1);
    }

    public function test_wildcards_in_the_search_are_taken_literally(): void
    {
        $user = $this->memberOf(['50% off', 'Globex', 'snake_case']);

        $this->actingAs($user)
            ->getJson(route('account-switcher', ['q' => '%']))
            ->assertJsonPath('accounts.0.name', '50% off')
            ->assertJsonPath('total', 1);

        $this->actingAs($user)
            ->getJson(route('account-switcher', ['q' => '_']))
            ->assertJsonPath('accounts.0.name', 'snake_case')
            ->assertJsonPath('total', 1);
    }

    public function test_it_never_lists_an_account_the_user_is_not_a_member_of(): void
    {
        $user = $this->memberOf(['Acme']);
        User::factory()->withAccount(Account::factory()->create(['name' => 'Acme Secret']))->create();

        $this->actingAs($user)
            ->getJson(route('account-switcher'))
            ->assertJsonPath('accounts.0.name', 'Acme')
            ->assertJsonPath('total', 1);

        $this->actingAs($user)
            ->getJson(route('account-switcher', ['q' => 'secret']))
            ->assertExactJson(['accounts' => [], 'total' => 0]);
    }

    public function test_a_search_that_is_not_a_short_string_is_rejected(): void
    {
        $user = $this->memberOf(['Acme']);

        $this->actingAs($user)
            ->getJson(route('account-switcher', ['q' => ['acme']]))
            ->assertUnprocessable();

        $this->actingAs($user)
            ->getJson(route('account-switcher', ['q' => str_repeat('a', 101)]))
            ->assertUnprocessable();
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson(route('account-switcher'))->assertUnauthorized();
    }
}
