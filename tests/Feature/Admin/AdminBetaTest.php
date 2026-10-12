<?php

namespace Tests\Feature\Admin;

use App\Mail\BetaInvite;
use App\Models\BetaAccessRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminBetaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->withAccount()->create();
    }

    public function test_it_lists_requests_by_status(): void
    {
        BetaAccessRequest::factory()->create();
        BetaAccessRequest::factory()->invited()->create();
        BetaAccessRequest::factory()->redeemed()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.beta.index', ['status' => 'invited']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/beta/index')
                ->has('invites.data', 1)
                ->where('invites.data.0.status', 'invited')
                ->where('counts', ['all' => 3, 'pending' => 1, 'invited' => 1, 'registered' => 1]));
    }

    public function test_an_admin_adds_someone_with_a_code_and_sends_nothing(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.beta.store'), ['email' => 'Team@Example.com', 'locale' => 'nl'])
            ->assertSessionHasNoErrors();

        $invite = BetaAccessRequest::query()->sole();
        $this->assertSame('team@example.com', $invite->email);
        $this->assertSame('nl', $invite->locale);
        $this->assertSame('pending', $invite->status());
        Mail::assertNothingOutgoing();
    }

    public function test_sending_mails_the_code_in_their_language(): void
    {
        Mail::fake();
        $invite = BetaAccessRequest::factory()->create(['locale' => 'nl']);

        $this->actingAs($this->admin)
            ->post(route('admin.beta.send', $invite))
            ->assertSessionHasNoErrors();

        Mail::assertQueued(BetaInvite::class, fn (BetaInvite $mail): bool => $mail->hasTo($invite->email)
            && $mail->locale === 'nl'
            && $mail->invite->is($invite));
        $this->assertSame('invited', $invite->refresh()->status());
    }

    public function test_an_invite_can_be_sent_again(): void
    {
        Mail::fake();
        $invite = BetaAccessRequest::factory()->invited()->create();

        $this->actingAs($this->admin)->post(route('admin.beta.send', $invite));

        Mail::assertQueued(BetaInvite::class, 1);
    }

    public function test_a_used_code_is_not_sent(): void
    {
        Mail::fake();
        $invite = BetaAccessRequest::factory()->redeemed()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.beta.send', $invite))
            ->assertSessionHasErrors('invite');

        Mail::assertNothingOutgoing();
    }

    public function test_the_mail_holds_the_code_and_the_link(): void
    {
        $invite = BetaAccessRequest::factory()->create();

        (new BetaInvite($invite))
            ->assertSeeInHtml($invite->code)
            ->assertSeeInHtml(route('register.invite', $invite->code));
    }

    public function test_a_request_can_be_deleted(): void
    {
        $invite = BetaAccessRequest::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.beta.destroy', $invite));

        $this->assertModelMissing($invite);
    }
}
