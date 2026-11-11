<?php

namespace Tests\Feature\Colleagues;

use App\Enums\EmailIntent;
use App\Enums\InterpretationOutcome;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Models\MailboxRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ColleaguePageTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $admin;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['domain' => 'noordkade.nl']);
        $this->admin = User::factory()->withAccount($this->account)->create();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->rules(senders: [
            'klant.nl' => SenderAccess::Answer,
            'hr@noordkade.nl' => SenderAccess::Ignore,
        ])->create(['email_address' => 'kennis@noordkade.nl']));
    }

    public function test_only_an_admin_of_the_account_sees_its_colleagues(): void
    {
        $member = User::factory()->withAccount($this->account, isAdmin: false)->create();

        $this->get(route('colleagues.index'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('colleagues.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('colleagues.index'))->assertOk();

        // The top bar offers the page to admins only.
        $this->actingAs($member)->get(route('inbox'))->assertInertia(fn (Assert $page) => $page->where('auth.isAccountAdmin', false));
        $this->actingAs($this->admin)->get(route('inbox'))->assertInertia(fn (Assert $page) => $page->where('auth.isAccountAdmin', true));
    }

    public function test_the_list_shows_what_howy_does_where_their_mails_stand_and_what_they_did(): void
    {
        $anna = $this->colleague('anna@noordkade.nl', ['name' => 'Anna de Boer', 'last_mail_at' => now()->subDay()]);
        $this->mail($anna, intent: EmailIntent::Question, outcome: InterpretationOutcome::Answered);
        $this->mail($anna, intent: EmailIntent::Information, outcome: InterpretationOutcome::Added);
        $this->mail($anna, intent: EmailIntent::Information, outcome: InterpretationOutcome::Duplicate);
        $this->colleague('piet@klant.nl', ['receives_mail' => false]);

        $this->actingAs($this->admin)
            ->get(route('colleagues.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('colleagues/index')
                ->where('total', 3)
                ->has('colleagues.data', 3)
                // Who mailed last comes first.
                ->where('colleagues.data.0.email', 'anna@noordkade.nl')
                ->where('colleagues.data.0.name', 'Anna de Boer')
                ->where('colleagues.data.0.access', 'learn_answer')
                ->where('colleagues.data.0.accessFrom', 'domain')
                ->where('colleagues.data.0.status', 'not_welcomed')
                ->where('colleagues.data.0.emailsCount', 3)
                ->where('colleagues.data.0.questionsCount', 1)
                ->where('colleagues.data.0.contributionsCount', 1)
                ->where('colleagues.data.1.email', 'hr@noordkade.nl')
                ->where('colleagues.data.1.access', 'ignore')
                ->where('colleagues.data.1.accessFrom', 'own')
                ->where('colleagues.data.1.status', 'ignored')
                ->where('colleagues.data.2.email', 'piet@klant.nl')
                ->where('colleagues.data.2.access', 'answer')
                ->where('colleagues.data.2.status', 'off')
                ->where('mailboxes', [['id' => $this->mailbox->id, 'emailAddress' => 'kennis@noordkade.nl']]));
    }

    public function test_the_list_filters_by_name_status_and_what_howy_does(): void
    {
        $this->colleague('anna@noordkade.nl', ['name' => 'Anna de Boer']);
        $this->colleague('piet@klant.nl', ['receives_mail' => false]);
        $this->colleague('eva@elders.nl');
        $welcomed = $this->colleague('jan@noordkade.nl');
        $welcomed->forceFill(['welcomed_at' => now()])->save();
        $gone = $this->colleague('tom@noordkade.nl');
        $gone->forceFill(['opted_out_at' => now()])->save();

        $emails = fn (array $query): array => $this->actingAs($this->admin)
            ->get(route('colleagues.index', $query))
            ->viewData('page')['props']['colleagues']['data'];
        $found = fn (array $query): array => collect($emails($query))->pluck('email')->sort()->values()->all();

        $this->assertSame(['anna@noordkade.nl'], $found(['q' => 'boer']));
        $this->assertSame(['piet@klant.nl'], $found(['q' => 'KLANT']));
        // Ignored, by a rule of their own or by none, nothing goes out to them.
        $this->assertSame(['anna@noordkade.nl'], $found(['status' => 'not_welcomed']));
        $this->assertSame(['eva@elders.nl', 'hr@noordkade.nl'], $found(['status' => 'ignored']));
        $this->assertSame(['jan@noordkade.nl'], $found(['status' => 'welcomed']));
        $this->assertSame(['tom@noordkade.nl'], $found(['status' => 'opted_out']));
        $this->assertSame(['piet@klant.nl'], $found(['status' => 'off']));
        $this->assertSame(['anna@noordkade.nl', 'jan@noordkade.nl', 'tom@noordkade.nl'], $found(['access' => 'learn_answer']));
        $this->assertSame(['piet@klant.nl'], $found(['access' => 'answer']));
        // Ignored by a rule of their own, or by no rule at all.
        $this->assertSame(['eva@elders.nl', 'hr@noordkade.nl'], $found(['access' => 'ignore']));
        $this->assertSame([], $found(['access' => 'learn']));
        // What is not a filter is no filter.
        $this->assertCount(6, $emails(['status' => 'whatever', 'access' => 'whatever']));
    }

    public function test_a_disconnected_mailbox_is_not_offered_and_who_had_it_has_no_mailbox(): void
    {
        $old = Tenancy::for($this->account, fn () => Mailbox::factory()->disconnected()->create(['email_address' => 'oud@noordkade.nl']));
        $anna = $this->colleague('anna@noordkade.nl', ['home_mailbox_id' => null]);
        $this->colleague('jan@noordkade.nl');

        $this->actingAs($this->admin)
            ->get(route('colleagues.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('withoutMailbox', 1)
                ->where('mailboxes', [['id' => $this->mailbox->id, 'emailAddress' => 'kennis@noordkade.nl']]));

        $statuses = fn (array $query): array => collect($this->actingAs($this->admin)
            ->get(route('colleagues.index', $query))
            ->viewData('page')['props']['colleagues']['data'])
            ->pluck('status', 'email')
            ->all();

        $this->assertSame(['anna@noordkade.nl' => 'no_mailbox'], $statuses(['status' => 'no_mailbox']));
        // Without a mailbox no rule applies to them, so no other filter finds them.
        $this->assertSame(['jan@noordkade.nl' => 'not_welcomed'], $statuses(['status' => 'not_welcomed']));
        $this->assertArrayNotHasKey('anna@noordkade.nl', $statuses(['status' => 'ignored']));
        $this->assertArrayNotHasKey('anna@noordkade.nl', $statuses(['access' => 'ignore']));

        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $anna))
            ->assertInertia(fn (Assert $page) => $page
                ->where('colleague.status', 'no_mailbox')
                ->where('colleague.homeMailbox', null)
                ->where('mailboxes', [['id' => $this->mailbox->id, 'emailAddress' => 'kennis@noordkade.nl']])
                ->has('access', 1)
                ->where('access.0.mailboxId', $this->mailbox->id));

        // Nor can it be chosen: nothing goes out from it.
        $this->actingAs($this->admin)
            ->patch(route('colleagues.update', $anna), ['home_mailbox_id' => $old->id])
            ->assertSessionHasErrors('home_mailbox_id');
        $this->actingAs($this->admin)
            ->patch(route('colleagues.update', $anna), ['access' => [$old->id => 'learn']])
            ->assertSessionHasErrors('access');

        $this->actingAs($this->admin)
            ->patch(route('colleagues.update', $anna), ['home_mailbox_id' => $this->mailbox->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->mailbox->id, Tenancy::for($this->account, fn () => $anna->refresh()->home_mailbox_id));
    }

    public function test_the_page_of_a_colleague_shows_their_access_per_mailbox_and_their_mail(): void
    {
        $support = Tenancy::for($this->account, fn () => Mailbox::factory()->rules(SenderAccess::Learn)->create(['email_address' => 'support@noordkade.nl']));
        $anna = $this->colleague('anna@noordkade.nl');
        $this->mail($anna, intent: EmailIntent::Question, outcome: InterpretationOutcome::Answered, subject: 'Vakantiedagen');
        Tenancy::for($this->account, fn () => MailboxRule::query()->create(['mailbox_id' => $support->id, 'colleague_id' => $anna->id, 'access' => SenderAccess::Answer]));

        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $anna))
            ->assertInertia(fn (Assert $page) => $page
                ->component('colleagues/show')
                ->where('colleague.email', 'anna@noordkade.nl')
                ->where('colleague.homeMailboxId', $this->mailbox->id)
                ->where('access', [
                    ['mailboxId' => $this->mailbox->id, 'emailAddress' => 'kennis@noordkade.nl', 'status' => 'active', 'own' => null, 'domain' => ['domain' => 'noordkade.nl', 'access' => 'learn_answer'], 'access' => 'learn_answer'],
                    ['mailboxId' => $support->id, 'emailAddress' => 'support@noordkade.nl', 'status' => 'active', 'own' => 'answer', 'domain' => ['domain' => 'noordkade.nl', 'access' => 'learn'], 'access' => 'answer'],
                ])
                ->has('emails', 1)
                ->where('emails.0.subject', 'Vakantiedagen'));
    }

    public function test_an_admin_adds_a_colleague(): void
    {
        $this->actingAs($this->admin)
            ->post(route('colleagues.store'), [
                'email' => ' Eva@Elders.nl ',
                'name' => 'Eva',
                'home_mailbox_id' => $this->mailbox->id,
                'access' => 'learn',
                'receives_mail' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Tenancy::for($this->account, function (): void {
            $eva = Colleague::query()->where('email', 'eva@elders.nl')->sole();

            $this->assertSame('Eva', $eva->name);
            $this->assertTrue($eva->receives_mail);
            $this->assertSame($this->mailbox->id, $eva->home_mailbox_id);
            $this->assertSame(SenderAccess::Learn, $this->mailbox->refresh()->access('eva@elders.nl'));
        });

        // Following the mailbox's rules writes no rule of their own.
        $this->actingAs($this->admin)
            ->post(route('colleagues.store'), ['email' => 'jan@noordkade.nl', 'home_mailbox_id' => $this->mailbox->id, 'access' => 'default', 'receives_mail' => '0'])
            ->assertSessionHasNoErrors();

        Tenancy::for($this->account, function (): void {
            $jan = Colleague::query()->where('email', 'jan@noordkade.nl')->sole();

            $this->assertFalse($jan->receives_mail);
            $this->assertSame(0, $jan->rules()->count());
        });
    }

    public function test_a_colleague_is_added_once_and_only_to_a_mailbox_of_the_account(): void
    {
        $this->colleague('anna@noordkade.nl');
        $theirs = Tenancy::for(Account::factory()->create(), fn () => Mailbox::factory()->create(['email_address' => 'kennis@elders.nl']));

        $this->actingAs($this->admin)
            ->post(route('colleagues.store'), ['email' => 'ANNA@noordkade.nl', 'home_mailbox_id' => $this->mailbox->id, 'access' => 'default'])
            ->assertSessionHasErrors('email');

        $this->actingAs($this->admin)
            ->post(route('colleagues.store'), ['email' => 'eva@elders.nl', 'home_mailbox_id' => $theirs->id, 'access' => 'default'])
            ->assertSessionHasErrors('home_mailbox_id');

        $this->actingAs($this->admin)
            ->post(route('colleagues.store'), ['email' => 'eva@elders.nl', 'home_mailbox_id' => $this->mailbox->id, 'access' => 'sometimes'])
            ->assertSessionHasErrors('access');

        Tenancy::for($this->account, function (): void {
            $this->assertSame(1, Colleague::query()->where('email', 'anna@noordkade.nl')->count());
            $this->assertFalse(Colleague::query()->where('email', 'eva@elders.nl')->exists());
        });
    }

    public function test_an_admin_changes_a_colleague(): void
    {
        $support = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']));
        $anna = $this->colleague('anna@noordkade.nl');

        $this->actingAs($this->admin)
            ->patch(route('colleagues.update', $anna), ['name' => 'Anna de Boer', 'home_mailbox_id' => $support->id, 'receives_mail' => '0'])
            ->assertSessionHasNoErrors();

        $anna = Tenancy::for($this->account, fn () => $anna->refresh());
        $this->assertSame('Anna de Boer', $anna->name);
        $this->assertSame($support->id, $anna->home_mailbox_id);
        $this->assertFalse($anna->receives_mail);
    }

    public function test_each_part_of_a_colleague_changes_on_its_own_where_it_shows(): void
    {
        $support = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']));
        $theirs = Tenancy::for(Account::factory()->create(), fn () => Mailbox::factory()->create(['email_address' => 'kennis@elders.nl']));
        $anna = $this->colleague('anna@noordkade.nl');

        // The name in the header, the switch beside it, the mailbox in the card.
        $this->actingAs($this->admin)->patch(route('colleagues.update', $anna), ['name' => 'Anna de Boer'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('colleagues.update', $anna), ['receives_mail' => false])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('colleagues.update', $anna), ['home_mailbox_id' => $support->id])->assertSessionHasNoErrors();

        $anna = Tenancy::for($this->account, fn () => $anna->refresh());
        $this->assertSame('Anna de Boer', $anna->name);
        $this->assertFalse($anna->receives_mail);
        $this->assertSame($support->id, $anna->home_mailbox_id);

        // Back to no name, and never another account's mailbox.
        $this->actingAs($this->admin)->patch(route('colleagues.update', $anna), ['name' => ''])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('colleagues.update', $anna), ['home_mailbox_id' => $theirs->id])->assertSessionHasErrors('home_mailbox_id');

        $anna = Tenancy::for($this->account, fn () => $anna->refresh());
        $this->assertNull($anna->name);
        $this->assertSame($support->id, $anna->home_mailbox_id);
    }

    public function test_the_page_says_whether_howy_is_live_and_when_the_follow_ups_come(): void
    {
        config(['adoption.how_it_works_after_workdays' => 3, 'adoption.nudge_after_workdays' => 7]);
        $anna = $this->colleague('anna@noordkade.nl');

        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $anna))
            ->assertInertia(fn (Assert $page) => $page
                ->where('isLive', false)
                ->where('followUpDays', ['how_it_works' => 3, 'nudge' => 7]));

        $this->account->forceFill(['live_at' => now()])->save();

        $this->actingAs($this->admin)
            ->get(route('colleagues.show', $anna))
            ->assertInertia(fn (Assert $page) => $page->where('isLive', true));
    }

    public function test_the_dialog_saves_the_name_and_access_per_mailbox_together(): void
    {
        $support = Tenancy::for($this->account, fn () => Mailbox::factory()->create(['email_address' => 'support@noordkade.nl']));
        $theirs = Tenancy::for(Account::factory()->create(), fn () => Mailbox::factory()->create(['email_address' => 'kennis@elders.nl']));
        $anna = $this->colleague('anna@noordkade.nl');

        $this->actingAs($this->admin)
            ->patch(route('colleagues.update', $anna), [
                'name' => 'Anna de Boer',
                'access' => [$this->mailbox->id => 'learn', $support->id => 'default'],
            ])
            ->assertSessionHasNoErrors();

        Tenancy::for($this->account, function () use ($anna, $support): void {
            $this->assertSame('Anna de Boer', $anna->refresh()->name);
            $this->assertSame(SenderAccess::Learn, $this->mailbox->refresh()->ruleFor('anna@noordkade.nl')?->access);
            $this->assertNull(MailboxRule::query()->where('mailbox_id', $support->id)->where('colleague_id', $anna->id)->first());
        });

        // Back to their domain's rule; never a rule on another account's mailbox.
        $this->actingAs($this->admin)
            ->patch(route('colleagues.update', $anna), ['access' => [$this->mailbox->id => 'default']])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->patch(route('colleagues.update', $anna), ['access' => [$theirs->id => 'learn']])
            ->assertSessionHasErrors('access');
        $this->actingAs($this->admin)
            ->patch(route('colleagues.update', $anna), ['access' => [$this->mailbox->id => 'sometimes']])
            ->assertSessionHasErrors("access.{$this->mailbox->id}");

        Tenancy::for($this->account, fn () => $this->assertNull(
            MailboxRule::query()->where('mailbox_id', $this->mailbox->id)->where('colleague_id', $anna->id)->first(),
        ));
        $this->assertSame(0, Tenancy::withoutTenancy(fn () => MailboxRule::query()->where('mailbox_id', $theirs->id)->whereNotNull('colleague_id')->count()));
    }

    public function test_access_per_mailbox_is_a_rule_of_their_own_or_none(): void
    {
        $anna = $this->colleague('anna@noordkade.nl');

        $this->actingAs($this->admin)
            ->put(route('colleagues.access.update', [$anna, $this->mailbox]), ['access' => 'ignore'])
            ->assertSessionHasNoErrors();

        $this->assertSame(SenderAccess::Ignore, Tenancy::for($this->account, fn () => $this->mailbox->refresh()->access('anna@noordkade.nl')));

        // Back to the rule for their domain.
        $this->actingAs($this->admin)
            ->put(route('colleagues.access.update', [$anna, $this->mailbox]), ['access' => 'default'])
            ->assertSessionHasNoErrors();

        Tenancy::for($this->account, function () use ($anna): void {
            $this->assertSame(SenderAccess::LearnAndAnswer, $this->mailbox->refresh()->access('anna@noordkade.nl'));
            $this->assertSame(0, $anna->rules()->count());
        });
    }

    public function test_another_accounts_colleagues_and_mailboxes_are_not_found(): void
    {
        [$theirColleague, $theirMailbox] = Tenancy::for(Account::factory()->create(), fn () => [
            Colleague::factory()->create(['email' => 'anna@noordkade.nl']),
            Mailbox::factory()->create(['email_address' => 'kennis@elders.nl']),
        ]);
        $ours = $this->colleague('anna@noordkade.nl');

        $this->actingAs($this->admin)->get(route('colleagues.show', $theirColleague))->assertNotFound();
        $this->actingAs($this->admin)->patch(route('colleagues.update', $theirColleague), ['name' => 'X', 'home_mailbox_id' => $this->mailbox->id])->assertNotFound();
        $this->actingAs($this->admin)->put(route('colleagues.access.update', [$theirColleague, $this->mailbox]), ['access' => 'ignore'])->assertNotFound();
        $this->actingAs($this->admin)->put(route('colleagues.access.update', [$ours, $theirMailbox]), ['access' => 'ignore'])->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('colleagues.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('total', 2)
                ->where('mailboxes', [['id' => $this->mailbox->id, 'emailAddress' => 'kennis@noordkade.nl']]));

        $this->assertNull(Tenancy::withoutTenancy(fn () => $theirColleague->refresh()->name === 'X' ? 'changed' : null));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function colleague(string $email, array $attributes = []): Colleague
    {
        return Tenancy::for($this->account, fn () => Colleague::query()->where('email', $email)->first()
            ?? Colleague::factory()->create(['email' => $email, 'name' => null, 'home_mailbox_id' => $this->mailbox->id, ...$attributes]));
    }

    private function mail(Colleague $colleague, EmailIntent $intent, InterpretationOutcome $outcome, ?string $subject = null): void
    {
        Tenancy::for($this->account, function () use ($colleague, $intent, $outcome, $subject): void {
            $email = Email::factory()->create(['mailbox_id' => $this->mailbox->id, 'from_email' => $colleague->email, 'subject' => $subject]);
            $email->colleague()->associate($colleague)->save();
            EmailInterpretation::factory()->create(['email_id' => $email->id, 'intent' => $intent, 'outcome' => $outcome]);
        });
    }
}
