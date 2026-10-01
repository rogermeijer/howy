<?php

namespace Tests\Feature\Mailboxes;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Models\Mailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\GmailFixtures;
use Tests\TestCase;

class BackfillEmailHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fetches_headers_only_for_emails_without_them(): void
    {
        Http::fake([
            GmailFixtures::API.'/messages/m1*' => Http::response(GmailFixtures::message('m1')),
            GmailFixtures::API.'/messages/gone*' => Http::response(['error' => ['code' => 404]], 404),
        ]);

        $account = Account::factory()->create();

        [$missing, $gone, $done] = Tenancy::for($account, function () {
            $mailbox = Mailbox::factory()->create();

            return [
                Email::factory()->create(['mailbox_id' => $mailbox->id, 'provider_message_id' => 'm1']),
                Email::factory()->create(['mailbox_id' => $mailbox->id, 'provider_message_id' => 'gone']),
                Email::factory()->create(['mailbox_id' => $mailbox->id, 'headers' => [['name' => 'From', 'value' => 'x']]]),
            ];
        });

        $this->artisan('emails:backfill-headers')->assertSuccessful();

        $this->assertSame('Message-ID', $missing->refresh()->headers[4]['name'] ?? null);
        $this->assertNull($gone->refresh()->headers);
        $this->assertSame([['name' => 'From', 'value' => 'x']], $done->refresh()->headers);
        Http::assertSentCount(2);
    }
}
