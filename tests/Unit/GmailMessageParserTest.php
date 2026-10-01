<?php

namespace Tests\Unit;

use App\Services\Gmail\GmailMessageParser;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\GmailFixtures;

class GmailMessageParserTest extends TestCase
{
    public function test_it_parses_headers_bodies_and_dates(): void
    {
        $parsed = (new GmailMessageParser)->parse(GmailFixtures::message('m1'));

        $this->assertSame('m1', $parsed['provider_message_id']);
        $this->assertSame('thread-m1', $parsed['provider_thread_id']);
        $this->assertSame('Tom Bakker', $parsed['from_name']);
        $this->assertSame('tom@haarlem.nl', $parsed['from_email']);
        $this->assertSame([['name' => null, 'email' => 'support@noordkade.nl']], $parsed['to']);
        $this->assertSame('Body of m1', $parsed['body_text']);
        $this->assertSame('<p>Body of m1</p>', $parsed['body_html']);
        $this->assertSame('<m1@mail.gmail.com>', $parsed['message_id_header']);

        // +0200 in the header, stored as UTC.
        $this->assertSame('2026-09-29 08:00:00', $parsed['sent_at']?->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $parsed['received_at']?->timezoneName);
    }

    public function test_it_keeps_every_header_in_order_with_repeats(): void
    {
        $message = GmailFixtures::message('m1');
        $message['payload']['headers'] = [
            ['name' => 'Received', 'value' => 'from mx1.haarlem.nl'],
            ['name' => 'Received', 'value' => 'from mx2.google.com'],
            ['name' => 'DKIM-Signature', 'value' => 'v=1; a=rsa-sha256; d=haarlem.nl'],
            ['name' => 'From', 'value' => 'Tom Bakker <tom@haarlem.nl>'],
            ['name' => 'X-Custom', 'value' => 'kept as-is'],
        ];

        $parsed = (new GmailMessageParser)->parse($message);

        $this->assertSame($message['payload']['headers'], $parsed['headers']);
        $this->assertSame('tom@haarlem.nl', $parsed['from_email']);
    }

    public function test_it_collects_attachments_from_nested_parts(): void
    {
        $message = GmailFixtures::message('m1');
        $message['payload'] = [
            'mimeType' => 'multipart/mixed',
            'headers' => $message['payload']['headers'],
            'parts' => [
                $message['payload'],
                [
                    'mimeType' => 'application/pdf',
                    'filename' => 'situatietekening.pdf',
                    'body' => ['size' => 84000, 'attachmentId' => 'att-1'],
                ],
            ],
        ];

        $parsed = (new GmailMessageParser)->parse($message);

        $this->assertTrue($parsed['has_attachments']);
        $this->assertSame([[
            'filename' => 'situatietekening.pdf',
            'mime_type' => 'application/pdf',
            'size' => 84000,
            'attachment_id' => 'att-1',
        ]], $parsed['attachments']);
        $this->assertSame('Body of m1', $parsed['body_text']);
    }

    public function test_it_converts_a_latin1_body_to_utf8(): void
    {
        $message = GmailFixtures::message('m1');
        $message['payload']['parts'][0]['headers'] = [['name' => 'Content-Type', 'value' => 'text/plain; charset=ISO-8859-1']];
        $message['payload']['parts'][0]['body']['data'] = GmailFixtures::encode((string) mb_convert_encoding('Geïmporteerd', 'ISO-8859-1', 'UTF-8'));

        $this->assertSame('Geïmporteerd', (new GmailMessageParser)->parse($message)['body_text']);
    }

    public function test_it_splits_address_lists_with_quoted_commas(): void
    {
        $addresses = (new GmailMessageParser)->addresses('"Bakker, Tom" <Tom@Haarlem.nl>, lotte@studio.nl, Sanne <sanne@noordkade.nl>');

        $this->assertSame([
            ['name' => 'Bakker, Tom', 'email' => 'tom@haarlem.nl'],
            ['name' => null, 'email' => 'lotte@studio.nl'],
            ['name' => 'Sanne', 'email' => 'sanne@noordkade.nl'],
        ], $addresses);
    }
}
