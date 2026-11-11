<?php

namespace Tests\Feature\Mail;

use App\Enums\SenderAccess;
use App\Mail\Content\MailRenderer;
use App\Mail\Content\RenderedMail;
use App\Mail\Flow\FlowMails;
use App\Mail\Flow\FlowRecipient;
use App\Mail\Previews\MailPreviews;
use Tests\TestCase;

/**
 * Every mail Howy sends, and every parked one, in the one frame: no image,
 * nothing loaded from elsewhere, a plain-text part, and only what the
 * reader's access allows.
 */
class MailDesignTest extends TestCase
{
    public function test_every_mail_renders_without_images_or_remote_resources_in_every_language(): void
    {
        $previews = app(MailPreviews::class);

        foreach ($previews->list() as $mail) {
            foreach (['en', 'nl'] as $locale) {
                foreach ($mail['access'] ? [SenderAccess::LearnAndAnswer, SenderAccess::Learn, SenderAccess::Answer] : [SenderAccess::LearnAndAnswer] as $access) {
                    $rendered = $previews->render($mail['key'], $locale, $access);
                    $label = "{$mail['key']} ({$locale}, {$access->value})";

                    $this->assertNotNull($rendered, $label);
                    $this->assertStringContainsString("<html lang=\"{$locale}\">", $rendered->html, $label);
                    $this->assertStringNotContainsString('<img', $rendered->html, $label);
                    $this->assertStringNotContainsString('cid:', $rendered->html, $label);
                    $this->assertStringNotContainsString('fonts.googleapis', $rendered->html, $label);
                    $this->assertStringNotContainsString('<link', $rendered->html, $label);
                    $this->assertStringNotContainsString('**', $rendered->html, $label);
                    $this->assertStringNotContainsString('**', $rendered->text, $label);
                    $this->assertNotSame('', trim($rendered->text), $label);
                    $this->assertNotSame('', trim($rendered->subject), $label);
                }
            }
        }

        $this->assertSame('en', app()->getLocale(), 'The locale is restored after rendering.');
    }

    public function test_the_welcome_says_only_what_the_access_allows(): void
    {
        $learns = $this->render(SenderAccess::Learn);
        $this->assertStringContainsString('Cc = save knowledge', $learns->text);
        $this->assertStringNotContainsString('To = find knowledge', $learns->text);
        $this->assertStringContainsString('I don\'t answer your questions', $learns->text);
        $this->assertStringNotContainsString('Ask me your first question', $learns->text);

        $answers = $this->render(SenderAccess::Answer);
        $this->assertStringContainsString('To = find knowledge', $answers->text);
        $this->assertStringNotContainsString('Cc = save knowledge', $answers->text);
        $this->assertStringContainsString('I don\'t keep anything from your emails', $answers->text);

        $both = $this->render(SenderAccess::LearnAndAnswer);
        $this->assertStringContainsString('Cc = save knowledge', $both->text);
        $this->assertStringContainsString('To = find knowledge', $both->text);
        $this->assertStringNotContainsString('Good to know', $both->text);
        $this->assertStringContainsString('mailto:howy@noordkade.nl?subject=My%20first%20question', $both->html);
    }

    public function test_the_welcome_greets_by_first_name_and_carries_the_way_out(): void
    {
        $rendered = $this->render(SenderAccess::LearnAndAnswer);

        $this->assertSame('Hi Sanne, I\'m Howy. I remember what you know together.', $rendered->subject);
        $this->assertStringContainsString('Hi Sanne,', $rendered->text);
        $this->assertStringContainsString('Unsubscribe: https://example.test/unsubscribe', $rendered->text);
        $this->assertStringContainsString('href="https://example.test/unsubscribe"', $rendered->html);
        $this->assertStringContainsString(route('privacy-policy'), $rendered->html);
    }

    public function test_the_first_save_shows_three_points_and_counts_the_rest(): void
    {
        $content = app(FlowMails::class)->firstSave($this->recipient(SenderAccess::Learn), 'Delivery', ['One.', 'Two.', 'Three.', 'Four.', 'Five.'], false, 'https://example.test/review');
        $rendered = app(MailRenderer::class)->render($content);

        $this->assertStringContainsString('Three.', $rendered->text);
        $this->assertStringNotContainsString('Four.', $rendered->text);
        $this->assertStringContainsString('…and 2 more points.', $rendered->text);
        $this->assertStringNotContainsString('takes a quick look', $rendered->text);
        $this->assertStringContainsString('Review or adjust: https://example.test/review', $rendered->text);
    }

    public function test_what_the_sender_wrote_is_escaped_and_only_our_bold_is_markup(): void
    {
        $content = app(FlowMails::class)->firstSave($this->recipient(SenderAccess::Learn), '<b>Subject</b>', ['<script>alert(1)</script>'], false, 'https://example.test/review');
        $html = app(MailRenderer::class)->html($content);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>Subject</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<strong style="font-weight: 700;">&quot;&lt;b&gt;Subject&lt;/b&gt;&quot;</strong>', $html);
    }

    private function render(SenderAccess $access): RenderedMail
    {
        return app(MailRenderer::class)->render(app(FlowMails::class)->welcome($this->recipient($access)));
    }

    private function recipient(SenderAccess $access): FlowRecipient
    {
        return new FlowRecipient('Sanne de Vries', $access, 'Noordkade', 'howy@noordkade.nl', 'https://example.test/unsubscribe');
    }
}
