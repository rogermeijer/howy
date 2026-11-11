<?php

namespace Tests\Feature\Mail;

use App\Enums\SenderAccess;
use App\Mail\Content\MailUrl;
use App\Mail\Previews\MailPreviews;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Links in a mail go to an address its recipient can reach: APP_PUBLIC_URL
 * when set. A mail linking to a .test host was silently dropped by a
 * recipient's spam filter.
 */
class MailUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_a_public_url_mails_link_to_the_app_url(): void
    {
        config(['app.public_url' => null]);

        $this->assertSame(route('privacy-policy'), MailUrl::route('privacy-policy'));

        // An unfilled https://${NGROK_DOMAIN} names no host: no public URL.
        config(['app.public_url' => 'https://']);

        $this->assertNull(MailUrl::root());
        $this->assertSame(route('privacy-policy'), MailUrl::route('privacy-policy'));
    }

    public function test_every_link_in_a_mail_goes_to_the_public_url(): void
    {
        config(['app.public_url' => 'https://howy.example/']);

        $welcome = app(MailPreviews::class)->render('welcome', 'en', SenderAccess::LearnAndAnswer);

        $this->assertNotNull($welcome);
        $this->assertStringContainsString('href="https://howy.example/privacy-policy"', $welcome->html);
        $this->assertStringContainsString('https://howy.example/privacy-policy', $welcome->text);
        $this->assertStringNotContainsString(rtrim((string) config('app.url'), '/').'/privacy-policy', $welcome->html.$welcome->text);

        $user = User::factory()->create(['email' => 'sanne@noordkade.nl']);
        $reset = (string) (new ResetPassword('reset-token'))->toMail($user)->render();

        $this->assertStringContainsString('https://howy.example/reset-password/reset-token', $reset);

        // The rest of the app keeps its own address.
        $this->assertSame(rtrim((string) config('app.url'), '/').'/privacy-policy', route('privacy-policy'));
    }

    public function test_the_verification_link_is_signed_for_the_public_url_and_opens_there(): void
    {
        config(['app.public_url' => 'https://howy.example']);
        $user = User::factory()->unverified()->create();

        $html = (string) (new VerifyEmail)->toMail($user)->render();

        $this->assertSame(1, preg_match('#href="(https://howy\.example/email/verify/[^"]+)"#', $html, $match));

        $this->actingAs($user)->get(html_entity_decode($match[1]))->assertRedirect();

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }
}
