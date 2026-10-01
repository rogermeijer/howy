<?php

namespace Tests\Unit;

use App\Services\Mail\EmailContentExtractor;
use PHPUnit\Framework\TestCase;

class EmailContentExtractorTest extends TestCase
{
    public function test_gmail_replies_are_split_at_the_gmail_quote(): void
    {
        $html = '<div dir="ltr">Bijgaand de tekeningen.<br><br>Groet, Sanne</div><br>'
            .'<div class="gmail_quote"><div dir="ltr" class="gmail_attr">On Tue, 29 Sep 2026 at 14:12, Tom Bakker &lt;tom.bakker@haarlem.nl&gt; wrote:<br></div>'
            .'<blockquote class="gmail_quote"><div dir="ltr">Graag ontvangen wij nog de situatietekening.</div><br>'
            .'<div class="gmail_quote"><div class="gmail_attr">On Wed, 23 Sep 2026 at 10:05, Sanne de Vries &lt;sanne@noordkade.nl&gt; wrote:<br></div>'
            .'<blockquote class="gmail_quote">Ik vraag ze op bij de architect.</blockquote></div></blockquote></div>';

        $result = (new EmailContentExtractor)->extract($html, null);

        $this->assertSame("Bijgaand de tekeningen.\n\nGroet, Sanne", $result['content_text']);
        $this->assertStringNotContainsString('situatietekening', (string) $result['content_html']);
        $this->assertCount(2, $result['quotes']);
        $this->assertSame([
            'text' => 'Graag ontvangen wij nog de situatietekening.',
            'name' => 'Tom Bakker',
            'email' => 'tom.bakker@haarlem.nl',
            'date' => '2026-09-29T14:12:00+00:00',
            'date_text' => 'Tue, 29 Sep 2026 at 14:12',
        ], $result['quotes'][0]);
        $this->assertSame('Sanne de Vries', $result['quotes'][1]['name']);
        $this->assertSame('Ik vraag ze op bij de architect.', $result['quotes'][1]['text']);
    }

    public function test_dutch_gmail_attribution_lines_are_understood(): void
    {
        $html = '<div>Prima!</div><div class="gmail_quote"><div class="gmail_attr">Op di 29 sep 2026 om 14:12 schreef Tom Bakker &lt;tom@haarlem.nl&gt;:<br></div>'
            .'<blockquote class="gmail_quote">Kan het vrijdag?</blockquote></div>';

        $quote = (new EmailContentExtractor)->extract($html, null)['quotes'][0];

        $this->assertSame('Tom Bakker', $quote['name']);
        $this->assertSame('tom@haarlem.nl', $quote['email']);
        $this->assertSame('di 29 sep 2026 om 14:12', $quote['date_text']);
    }

    public function test_apple_mail_and_spark_quotes_are_split_level_by_level(): void
    {
        // As stored from Spark: each level a messageReplySection with a cite blockquote.
        $html = '<html><body><div name="messageBodySection"><div><span style="font-family:Arial;">Yes, het werkt!</span></div></div>'
            .'<div name="messageReplySection">On 1 Oct 2026 at 13:24 +0200, Roger Meijer &lt;rogermeijer@gmail.com&gt;, wrote:<br />'
            .'<blockquote type="cite"><div name="messageBodySection"><div>5 keer dan?</div></div>'
            .'<div name="messageReplySection">On 1 Oct 2026 at 13:20 +0200, Roger Meijer &lt;rogermeijer@gmail.com&gt;, wrote:<br />'
            .'<blockquote type="cite"><div name="messageBodySection"><div>Toch 4x</div></div></blockquote></div>'
            .'</blockquote></div></body></html>';

        $result = (new EmailContentExtractor)->extract($html, null);

        $this->assertSame('Yes, het werkt!', $result['content_text']);
        $this->assertSame(['5 keer dan?', 'Toch 4x'], array_column($result['quotes'], 'text'));
        $this->assertSame('2026-10-01T11:24:00+00:00', $result['quotes'][0]['date']);
        $this->assertSame('rogermeijer@gmail.com', $result['quotes'][0]['email']);
    }

    public function test_an_attribution_line_outside_the_blockquote_is_removed_from_the_content(): void
    {
        $html = '<div>Akkoord.</div><div><br></div><div>On 29 Sep 2026, at 14:12, Lotte Hendriks &lt;lotte@studio.nl&gt; wrote:</div>'
            .'<blockquote type="cite"><div>RAL 7016 voor de gevel?</div></blockquote>';

        $result = (new EmailContentExtractor)->extract($html, null);

        $this->assertSame('Akkoord.', $result['content_text']);
        $this->assertSame('Lotte Hendriks', $result['quotes'][0]['name']);
        $this->assertSame('RAL 7016 voor de gevel?', $result['quotes'][0]['text']);
    }

    public function test_outlook_replies_are_split_at_the_reply_header(): void
    {
        $html = '<div>Ontvangen, dank.</div><hr>'
            .'<div id="divRplyFwdMsg"><b>From:</b> Tom Bakker &lt;tom@haarlem.nl&gt;<br><b>Sent:</b> Tuesday, September 29, 2026 2:12 PM<br>'
            .'<b>To:</b> Sanne &lt;sanne@noordkade.nl&gt;<br><b>Subject:</b> Vergunning</div>'
            .'<div>Graag uiterlijk 3 oktober.</div>';

        $result = (new EmailContentExtractor)->extract($html, null);

        $this->assertSame('Ontvangen, dank.', $result['content_text']);
        $this->assertSame('Tom Bakker', $result['quotes'][0]['name']);
        $this->assertSame('tom@haarlem.nl', $result['quotes'][0]['email']);
        $this->assertSame('2026-09-29T14:12:00+00:00', $result['quotes'][0]['date']);
        $this->assertSame('Graag uiterlijk 3 oktober.', $result['quotes'][0]['text']);
    }

    public function test_plain_text_mail_is_split_on_attribution_and_quote_markers(): void
    {
        $text = "Prima, tot vrijdag.\n\nOn 29 Sep 2026, at 14:12, Tom Bakker <tom@haarlem.nl> wrote:\n> Kan het vrijdag?\n>\n> On 28 Sep 2026, at 09:00, Sanne <sanne@noordkade.nl> wrote:\n>> Wanneer kom je langs?";

        $result = (new EmailContentExtractor)->extract(null, $text);

        $this->assertNull($result['content_html']);
        $this->assertSame('Prima, tot vrijdag.', $result['content_text']);
        $this->assertSame(['Kan het vrijdag?', 'Wanneer kom je langs?'], array_column($result['quotes'], 'text'));
        $this->assertSame(['Tom Bakker', 'Sanne'], array_column($result['quotes'], 'name'));
    }

    public function test_content_html_is_sanitised(): void
    {
        $html = '<p style="color:red" onclick="steal()">Hallo <a href="javascript:alert(1)">klik</a> '
            .'<a href="https://haarlem.nl">site</a></p><img src="https://tracker.example/p.gif"><script>alert(1)</script>';

        $content = (string) (new EmailContentExtractor)->extract($html, null)['content_html'];

        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('style=', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('<img', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringContainsString('href="https://haarlem.nl"', $content);
        $this->assertStringContainsString('target="_blank"', $content);
    }

    public function test_mail_without_a_quote_keeps_all_its_content(): void
    {
        $result = (new EmailContentExtractor)->extract('<p>Eerste bericht.</p><ul><li>een</li><li>twee</li><li>drie</li></ul>', null);

        $this->assertSame("Eerste bericht.\n\n• een\n• twee\n• drie", $result['content_text']);
        $this->assertSame([], $result['quotes']);
    }
}
