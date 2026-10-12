<?php

namespace Tests\Feature\Admin;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\Gmail\MailboxConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->withAccount()->create();
    }

    public function test_it_lists_every_account(): void
    {
        $theirs = Account::factory()->create(['name' => 'Someone Else']);
        User::factory()->withAccount($theirs)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/accounts/index')
                ->has('accounts.data', 2)
                ->where('accounts.data', fn ($accounts) => collect($accounts)->contains(
                    fn (array $account): bool => $account['name'] === 'Someone Else' && $account['members_count'] === 1,
                )));
    }

    public function test_it_creates_an_account_without_moving_the_owner(): void
    {
        $owner = User::factory()->withAccount()->create();
        $current = $owner->active_account_id;

        $this->actingAs($this->admin)
            ->post(route('admin.accounts.store'), [
                'name' => 'New Co',
                'locale' => 'nl',
                'timezone' => 'Europe/Amsterdam',
                'owner_email' => $owner->email,
            ])
            ->assertSessionHasNoErrors();

        $account = Account::query()->where('name', 'New Co')->sole();

        $this->assertTrue($owner->isAdminOf($account));
        $this->assertSame($current, $owner->refresh()->active_account_id);
    }

    public function test_an_owner_without_an_account_is_pointed_at_the_new_one(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.accounts.store'), [
            'name' => 'First Co',
            'locale' => 'en',
            'timezone' => 'UTC',
            'owner_email' => $owner->email,
        ]);

        $this->assertSame(Account::query()->where('name', 'First Co')->sole()->id, $owner->refresh()->active_account_id);
    }

    public function test_the_owner_must_exist(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.accounts.store'), [
                'name' => 'Nobody Co',
                'locale' => 'en',
                'timezone' => 'UTC',
                'owner_email' => 'nobody@example.com',
            ])
            ->assertSessionHasErrors('owner_email');
    }

    public function test_it_updates_an_account(): void
    {
        $account = Account::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.accounts.update', $account), ['name' => 'Renamed', 'locale' => 'nl', 'timezone' => 'Europe/Amsterdam'])
            ->assertRedirect(route('admin.accounts.show', $account));

        $this->assertSame('Renamed', $account->refresh()->name);
        $this->assertSame('Europe/Amsterdam', $account->timezone);
    }

    public function test_it_manages_members(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.accounts.members.store', $account), ['email' => $user->email, 'is_admin' => false])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->belongsToAccount($account));
        $this->assertFalse($user->refresh()->isAdminOf($account));
        $this->assertSame($account->id, $user->active_account_id);

        $this->post(route('admin.accounts.members.store', $account), ['email' => $user->email])
            ->assertSessionHasErrors('email');

        $this->patch(route('admin.accounts.members.update', [$account, $user]), ['is_admin' => true]);
        $this->assertTrue($user->refresh()->isAdminOf($account));

        $this->delete(route('admin.accounts.members.destroy', [$account, $user]));
        $this->assertFalse($user->belongsToAccount($account));
        $this->assertNull($user->refresh()->active_account_id);
    }

    public function test_a_non_member_cannot_be_updated_or_removed(): void
    {
        $account = Account::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($this->admin);

        $this->patch(route('admin.accounts.members.update', [$account, $stranger]), ['is_admin' => true])->assertNotFound();
        $this->delete(route('admin.accounts.members.destroy', [$account, $stranger]))->assertNotFound();
    }

    public function test_deleting_needs_the_name_typed_over(): void
    {
        $account = Account::factory()->create(['name' => 'Keep Me']);

        $this->actingAs($this->admin)
            ->delete(route('admin.accounts.destroy', $account), ['confirm_name' => 'keep me'])
            ->assertSessionHasErrors('confirm_name');

        $this->assertModelExists($account);
    }

    public function test_deleting_takes_its_data_files_and_mailboxes_with_it(): void
    {
        Storage::fake('knowledge');
        $this->mock(MailboxConnector::class, fn (MockInterface $mock) => $mock->shouldReceive('disconnect')->once());

        $account = Account::factory()->create(['name' => 'Gone Co']);
        $member = User::factory()->withAccount($account)->create();
        $email = Tenancy::for($account, fn () => Email::factory()->create([
            'mailbox_id' => Mailbox::factory()->create()->id,
        ]));
        Storage::disk('knowledge')->put("accounts/{$account->id}/documents/1/v1/file.pdf", 'pdf');

        $this->actingAs($this->admin)
            ->delete(route('admin.accounts.destroy', $account), ['confirm_name' => 'Gone Co'])
            ->assertRedirect(route('admin.accounts.index'));

        $this->assertModelMissing($account);
        $this->assertModelExists($member);
        $this->assertNull($member->refresh()->active_account_id);
        $this->assertFalse(Tenancy::withoutTenancy(fn () => Email::query()->whereKey($email->id)->exists()));
        Storage::disk('knowledge')->assertMissing("accounts/{$account->id}/documents/1/v1/file.pdf");
    }
}
