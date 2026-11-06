<?php

namespace Tests\Feature\Mailboxes;

use App\Enums\MailboxStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailboxImportOfferTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->admin = User::factory()->withAccount($this->account)->create();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'kennis@noordkade.nl']));
    }

    public function test_a_mailbox_that_never_imported_offers_its_last_30_days_to_an_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page->where('mailboxes.0.importOffered', true));

        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->actingAs($member)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page->where('mailboxes.0.importOffered', false));
    }

    public function test_no_need_asks_no_more_and_imports_nothing(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->delete(route('mailboxes.import-offer.destroy', $this->mailbox))
            ->assertRedirect();

        Queue::assertNothingPushed();
        $this->assertNotNull($this->mailbox->refresh()->import_offer_answered_at);
        $this->assertFalse($this->mailbox->offersImport());

        $this->actingAs($this->admin)
            ->get(route('settings'))
            ->assertInertia(fn (Assert $page) => $page->where('mailboxes.0.importOffered', false));
    }

    public function test_an_import_ends_the_offer(): void
    {
        // What the import dialog leaves behind once it imported something.
        $this->mailbox->forceFill(['import_batch_id' => 'batch-1'])->save();

        $this->assertFalse($this->mailbox->offersImport());
        $this->assertNull($this->mailbox->import_offer_answered_at);
    }

    public function test_a_mailbox_that_needs_reconnecting_offers_nothing(): void
    {
        $this->mailbox->forceFill(['status' => MailboxStatus::NeedsReauth])->save();

        $this->assertFalse($this->mailbox->offersImport());
    }

    public function test_only_an_admin_of_the_account_turns_it_down(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();
        $this->actingAs($member)->delete(route('mailboxes.import-offer.destroy', $this->mailbox))->assertForbidden();

        // Another account's mailbox does not exist for an admin elsewhere.
        $outsider = User::factory()->withAccount()->create();
        $this->actingAs($outsider)->delete(route('mailboxes.import-offer.destroy', $this->mailbox))->assertNotFound();

        $this->assertTrue($this->mailbox->refresh()->offersImport());
    }
}
