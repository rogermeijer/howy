<?php

namespace Tests\Feature\Admin;

use App\Mail\RenderedMailable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminMailPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_sees_every_mail_rendered_with_sample_data(): void
    {
        $admin = User::factory()->superAdmin()->withAccount()->create();

        $this->actingAs($admin)
            ->get(route('admin.mails.index', ['mail' => 'how-it-works', 'locale' => 'nl', 'access' => 'learn']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/mails/index')
                ->where('selected', ['mail' => 'how-it-works', 'locale' => 'nl', 'access' => 'learn'])
                ->where('preview.subject', 'Zet me in de Cc om te bewaren wat je weet. Meer hoef je niet te weten.')
                ->where('mails', fn ($mails): bool => collect($mails)->pluck('group')->unique()->sort()->values()->all() === ['flow', 'parked', 'reply', 'system']));
    }

    public function test_an_unknown_mail_is_refused(): void
    {
        $admin = User::factory()->superAdmin()->withAccount()->create();

        $this->actingAs($admin)
            ->get(route('admin.mails.index', ['mail' => 'nope']))
            ->assertSessionHasErrors('mail');
    }

    public function test_a_test_goes_to_the_super_admin_only(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->withAccount()->create(['email' => 'admin@howy.test']);

        $this->actingAs($admin)
            ->post(route('admin.mails.send'), ['mail' => 'welcome', 'locale' => 'nl', 'access' => 'learn_answer'])
            ->assertRedirect();

        Mail::assertSent(RenderedMailable::class, fn (RenderedMailable $mail): bool => $mail->hasTo('admin@howy.test')
            && $mail->mail->subject === 'Hoi Sanne, ik ben Howy. Ik onthoud wat jullie samen weten.');
        Mail::assertSentCount(1);
    }

    public function test_someone_who_is_not_a_super_admin_cannot_see_or_send(): void
    {
        Mail::fake();
        $user = User::factory()->withAccount()->create();

        $this->actingAs($user)->get(route('admin.mails.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.mails.send'), ['mail' => 'welcome'])->assertForbidden();

        Mail::assertNothingSent();
    }
}
