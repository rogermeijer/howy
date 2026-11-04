<?php

namespace Tests\Feature\Mail;

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Production sends through Resend's API (resend/resend-php), not SMTP. If the
 * SDK disappears from composer the mailer fails to build only at send time.
 */
class ResendMailerTest extends TestCase
{
    public function test_the_resend_mailer_builds_the_api_transport(): void
    {
        config(['services.resend.key' => 're_test_key']);

        $this->assertInstanceOf(ResendTransport::class, Mail::mailer('resend')->getSymfonyTransport());
    }
}
