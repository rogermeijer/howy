<?php

namespace Tests\Feature\Mailboxes;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Models\Mailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExtractEmailContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_emails_are_split_again_without_calling_gmail(): void
    {
        Http::preventStrayRequests();

        $account = Account::factory()->create();

        $email = Tenancy::for($account, fn () => Email::factory()->create([
            'mailbox_id' => Mailbox::factory()->create()->id,
            'body_html' => '<div>Prima!</div><blockquote type="cite">Kan het vrijdag?</blockquote>',
        ]));

        $this->artisan('emails:extract-content')->assertSuccessful();

        $email->refresh();

        $this->assertSame('Prima!', $email->content_text);
        $this->assertSame('Kan het vrijdag?', $email->quotes[0]['text'] ?? null);
    }
}
