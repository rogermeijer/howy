<?php

namespace App\Mail\Content;

use Illuminate\Support\HtmlString;

/**
 * Renders MailContent: the HTML through resources/views/mail/content.blade.php,
 * the plain text from the text each block wrote when it was added.
 */
class MailRenderer
{
    public function render(MailContent $content): RenderedMail
    {
        return new RenderedMail($content->subject, $this->html($content), $this->text($content));
    }

    public function html(MailContent $content): string
    {
        return view('mail.content', ['content' => $content])->render();
    }

    public function text(MailContent $content): string
    {
        $parts = array_map(fn (array $block): string => self::plain($block['text']), $content->blocks());

        if ($content->footerReason() !== null) {
            $footer = ['-- ', self::plain($content->footerReason()), __('Privacy policy: :url', ['url' => MailUrl::route('privacy-policy')])];

            if ($content->unsubscribeUrl() !== null) {
                $footer[] = __('Unsubscribe: :url', ['url' => $content->unsubscribeUrl()]);
            }

            $parts[] = implode("\n", $footer);
        }

        return implode("\n\n", array_filter($parts, fn (string $part): bool => $part !== ''))."\n";
    }

    /**
     * Text with **bold** marked up, everything else escaped.
     */
    public static function inline(string $text): HtmlString
    {
        return new HtmlString((string) preg_replace('/\*\*(.+?)\*\*/s', '<strong style="font-weight: 700;">$1</strong>', e($text)));
    }

    /**
     * Text without its **bold** markers, for the plain-text part.
     */
    public static function plain(string $text): string
    {
        return (string) preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
    }
}
