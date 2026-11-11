<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The password reset and verification mails come in Howy's frame and voice,
 * with the framework's links in them.
 */
class AccountMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_password_reset_mail_is_howys_with_the_reset_link(): void
    {
        $user = User::factory()->create(['name' => 'Sanne de Vries', 'email' => 'sanne@noordkade.nl']);

        $message = (new ResetPassword('reset-token'))->toMail($user);
        $html = (string) $message->render();

        $this->assertSame('Reset your Howy password', $message->subject);
        $this->assertStringContainsString('Hi Sanne,', $html);
        $this->assertStringContainsString('Choose a new password', $html);
        $this->assertStringContainsString(e(url(route('password.reset', ['token' => 'reset-token', 'email' => 'sanne@noordkade.nl'], false))), $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_the_verification_mail_is_howys_with_the_signed_link(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Sanne de Vries']);

        $message = (new VerifyEmail)->toMail($user);
        $html = (string) $message->render();

        $this->assertSame('Confirm your email address', $message->subject);
        $this->assertStringContainsString('Confirm my email address', $html);
        $this->assertStringContainsString('/email/verify/'.$user->id.'/', $html);
        $this->assertStringContainsString('signature=', $html);
    }
}
