<?php

namespace Tests\Feature\Adoption;

use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Colleague;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use App\Models\User;
use App\Services\Mail\HowyMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesGmailSending;
use Tests\TestCase;

/**
 * How it works (02) and the nudge (06), counted in working days from the
 * welcome. 13 November 2026 is a Friday.
 */
class FollowUpTest extends TestCase
{
    use FakesGmailSending;
    use RefreshDatabase;

    private Account $account;

    private Mailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeGmailSend('sent-1', 'thread-1');
        Http::preventStrayRequests();
        config(['inertia.ssr.enabled' => false]);

        $this->account = Account::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $this->account->forceFill(['live_at' => now()])->save();
        $this->mailbox = Tenancy::for($this->account, fn () => Mailbox::factory()->create([
            'email_address' => 'howy@noordkade.nl',
            'token_expires_at' => now()->addYear(),
        ]));
    }

    public function test_how_it_works_follows_two_working_days_after_the_welcome(): void
    {
        $anna = $this->welcomed('anna@noordkade.nl', '2026-11-13 10:00');

        // Monday is one working day on; Tuesday before ten is not two yet.
        $this->runAt('2026-11-16 10:30');
        $this->runAt('2026-11-17 09:30');
        $this->assertSame([], $this->kinds($anna));

        $this->runAt('2026-11-17 10:30');
        $this->assertSame(['how_it_works'], $this->kinds($anna));
        $this->assertSame('2026-11-17', Tenancy::for($this->account, fn () => $anna->refresh()->explained_at?->toDateString()));

        // Once.
        $this->runAt('2026-11-17 11:30');
        $this->runAt('2026-11-18 10:30');
        $this->assertSame(['how_it_works'], $this->kinds($anna));
        $this->assertSame(1, $this->gmailSendCount());
    }

    public function test_the_interval_comes_from_the_config(): void
    {
        config(['adoption.how_it_works_after_workdays' => 4]);
        $anna = $this->welcomed('anna@noordkade.nl', '2026-11-13 10:00');

        $this->runAt('2026-11-18 10:30');
        $this->assertSame([], $this->kinds($anna));

        $this->runAt('2026-11-19 10:30');
        $this->assertSame(['how_it_works'], $this->kinds($anna));
    }

    public function test_nothing_goes_outside_working_time(): void
    {
        $anna = $this->welcomed('anna@noordkade.nl', '2026-11-11 10:00');

        // Due Friday at ten; Friday evening and the weekend are no working time.
        $this->runAt('2026-11-13 18:00');
        $this->runAt('2026-11-14 10:30');
        $this->assertSame([], $this->kinds($anna));

        $this->runAt('2026-11-16 09:00');
        $this->assertSame(['how_it_works'], $this->kinds($anna));
    }

    public function test_the_nudge_goes_to_someone_howy_learns_from_who_has_not_mailed_since(): void
    {
        $silent = $this->welcomed('anna@noordkade.nl', '2026-11-16 10:00', explained: true);
        $active = $this->welcomed('jan@noordkade.nl', '2026-11-16 10:00', explained: true);
        $asker = $this->welcomed('piet@noordkade.nl', '2026-11-16 10:00', explained: true);

        Tenancy::for($this->account, function () use ($active, $asker): void {
            $active->forceFill(['last_mail_at' => Carbon::parse('2026-11-17 14:00', 'Europe/Amsterdam')->utc()])->save();
            $this->mailbox->rules()->create(['colleague_id' => $asker->id, 'access' => SenderAccess::Answer]);
        });

        $this->runAt('2026-11-20 10:30');
        $this->assertSame([], $this->kinds($silent));

        // Five working days after Monday at ten: the next Monday.
        $this->runAt('2026-11-23 10:30');

        $this->assertSame(['nudge'], $this->kinds($silent));
        $this->assertSame([], $this->kinds($active));
        $this->assertSame([], $this->kinds($asker));
        $this->assertNotNull(Tenancy::for($this->account, fn () => $silent->refresh()->nudged_at));
    }

    public function test_one_follow_up_a_day_at_most(): void
    {
        // Welcomed long ago, while Howy was paused: both are due at once.
        $anna = $this->welcomed('anna@noordkade.nl', '2026-11-02 10:00');

        $this->runAt('2026-11-16 10:30');
        $this->runAt('2026-11-16 12:30');
        $this->assertSame(['how_it_works'], $this->kinds($anna));

        $this->runAt('2026-11-17 10:30');
        $this->assertSame(['how_it_works', 'nudge'], $this->kinds($anna));
    }

    public function test_no_follow_up_while_paused_or_for_someone_who_may_not_get_it(): void
    {
        $off = $this->welcomed('jan@noordkade.nl', '2026-11-13 10:00');
        $gone = $this->welcomed('tom@noordkade.nl', '2026-11-13 10:00');
        Tenancy::for($this->account, function () use ($off, $gone): void {
            $off->forceFill(['receives_mail' => false])->save();
            $gone->forceFill(['opted_out_at' => now()])->save();
        });
        $anna = $this->welcomed('anna@noordkade.nl', '2026-11-13 10:00');
        $this->account->forceFill(['live_at' => null])->save();

        $this->runAt('2026-11-17 10:30');

        $this->assertSame(0, Tenancy::for($this->account, fn () => OutboundMail::query()->count()));
        $this->assertSame([], $this->kinds($anna));
    }

    public function test_the_colleague_page_says_when_each_follow_up_is_planned(): void
    {
        $admin = User::factory()->withAccount($this->account)->create();
        $anna = $this->welcomed('anna@noordkade.nl', '2026-11-13 10:00');

        $this->actingAs($admin)
            ->get(route('colleagues.show', $anna))
            ->assertInertia(fn (Assert $page) => $page
                ->where('planned.how_it_works', Carbon::parse('2026-11-17 10:00', 'Europe/Amsterdam')->toIso8601String())
                ->where('planned.nudge', Carbon::parse('2026-11-20 10:00', 'Europe/Amsterdam')->toIso8601String()));
    }

    public function test_a_mail_another_sender_holds_is_left_to_it(): void
    {
        $anna = $this->welcomed('anna@noordkade.nl', '2026-11-13 10:00');
        $mail = Tenancy::for($this->account, fn () => OutboundMail::factory()->create([
            'mailbox_id' => $this->mailbox->id, 'colleague_id' => $anna->id, 'recipient' => $anna->email,
            'kind' => OutboundMailKind::HowItWorks, 'status' => OutboundMailStatus::Pending,
        ]));

        // The hourly command and a job at once: the one without the lock backs off.
        $lock = Cache::lock("outbound-mail:{$mail->id}", 120);
        $lock->get();
        Tenancy::for($this->account, fn () => app(HowyMailer::class)->deliver($mail));
        $lock->release();

        $this->assertSame(OutboundMailStatus::Pending, Tenancy::for($this->account, fn () => $mail->refresh()->status));
        $this->assertSame(0, $this->gmailSendCount());
    }

    private function welcomed(string $email, string $at, bool $explained = false): Colleague
    {
        return Tenancy::for($this->account, function () use ($email, $at, $explained): Colleague {
            $colleague = Colleague::factory()->create(['email' => $email, 'home_mailbox_id' => $this->mailbox->id]);
            $colleague->forceFill([
                // Stored in UTC, as the app stores every time.
                'welcomed_at' => Carbon::parse($at, 'Europe/Amsterdam')->utc(),
                'explained_at' => $explained ? Carbon::parse($at, 'Europe/Amsterdam')->utc() : null,
            ])->save();

            return $colleague;
        });
    }

    private function runAt(string $local): void
    {
        $this->travelTo(Carbon::parse($local, 'Europe/Amsterdam'));
        $this->artisan('adoption:send')->assertSuccessful();
    }

    /**
     * The kinds of Howy's mails that went to them, in order.
     *
     * @return list<string>
     */
    private function kinds(Colleague $colleague): array
    {
        return Tenancy::for($this->account, fn () => OutboundMail::query()
            ->where('colleague_id', $colleague->id)
            ->where('status', OutboundMailStatus::Sent)
            ->orderBy('id')
            ->get()
            ->map(fn (OutboundMail $mail): string => $mail->kind->value)
            ->all());
    }
}
