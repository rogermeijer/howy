<?php

namespace App\Services\Mail;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Carbon;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Throwable;

/**
 * Splits an email into what its sender actually wrote and the history it quotes.
 *
 * Replies drag the whole conversation along. Mail clients mark where the quote
 * starts, each in their own way, so the HTML is cut at the first marker found:
 *
 * - Gmail: `.gmail_quote`
 * - Apple Mail, Thunderbird: `<blockquote type="cite">`
 * - Spark: `[name=messageReplySection]`
 * - Outlook: `#divRplyFwdMsg`, `#mail-editor-reference-message-container`
 * - Yahoo, Proton: `.yahoo_quoted`, `.protonmail_quote`
 *
 * The quoted part is split again, level by level, into one entry per earlier
 * message with the sender and date read from its "On … wrote:" line. Plain-text
 * mail gets the same treatment from its "On … wrote:" and ">" lines.
 *
 * The new content is sanitised before it is stored: no scripts, styles, event
 * handlers or images (remote images are mostly tracking pixels), and links open
 * in a new tab.
 */
class EmailContentExtractor
{
    private const int MAX_DEPTH = 12;

    private const string QUOTE_MARKERS = "//*[contains(concat(' ', normalize-space(@class), ' '), ' gmail_quote ')]"
        ." | //blockquote[@type='cite']"
        ." | //*[@name='messageReplySection']"
        ." | //*[@id='divRplyFwdMsg']"
        ." | //*[@id='mail-editor-reference-message-container']"
        ." | //*[contains(concat(' ', normalize-space(@class), ' '), ' yahoo_quoted ')]"
        ." | //*[contains(concat(' ', normalize-space(@class), ' '), ' protonmail_quote ')]";

    /** "On … wrote:" in the languages our users mail in. */
    private const string ATTRIBUTION = '/^\s*(?:On|Op|Am|Le|El|Il)\s.+(?:wrote|schreef|schrieb|a écrit|escribió|ha scritto).*:\s*$/isu';

    private const array BLOCK_ELEMENTS = [
        'p', 'div', 'section', 'article', 'header', 'footer', 'blockquote', 'pre', 'table', 'tr',
        'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr',
    ];

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowRelativeLinks(false)
                ->dropElement('img')
                ->dropElement('style')
                ->dropElement('script')
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->forceAttribute('a', 'target', '_blank')
                ->withMaxInputLength(1_000_000),
        );
    }

    /**
     * @return array{
     *     content_html: string|null,
     *     content_text: string|null,
     *     quotes: list<array{name: string|null, email: string|null, date: string|null, date_text: string|null, text: string}>,
     * }
     */
    public function extract(?string $html, ?string $text): array
    {
        if ($html !== null && trim($html) !== '') {
            [$content, $quotes] = $this->splitHtml($html, 0);

            $contentText = $this->toText($content);

            return [
                'content_html' => $contentText === '' ? null : trim($this->sanitizer->sanitize($content)),
                'content_text' => $contentText === '' ? null : $contentText,
                'quotes' => $quotes,
            ];
        }

        if ($text !== null && trim($text) !== '') {
            [$content, $quotes] = $this->splitText($text, 0);

            return [
                'content_html' => null,
                'content_text' => $content === '' ? null : $content,
                'quotes' => $quotes,
            ];
        }

        return ['content_html' => null, 'content_text' => null, 'quotes' => []];
    }

    /**
     * @return array{0: string, 1: list<array{name: string|null, email: string|null, date: string|null, date_text: string|null, text: string}>}
     */
    private function splitHtml(string $html, int $depth): array
    {
        $doc = $this->load($html);
        $body = $this->body($doc);

        foreach (['script', 'style', 'title', 'head'] as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $markers = (new DOMXPath($doc))->query(self::QUOTE_MARKERS);
        $marker = $markers === false ? null : $markers->item(0);

        if (! $marker instanceof DOMElement || $depth >= self::MAX_DEPTH) {
            return [$this->innerHtml($body), []];
        }

        // Everything from the marker on is history; so is an "On … wrote:" line
        // sitting just before it (Apple Mail puts it outside the blockquote).
        $fragment = $doc->createElement('div');
        $parent = $marker->parentNode;
        $attribution = $this->precedingAttribution($marker);

        if ($attribution !== null) {
            $fragment->appendChild($attribution);
        }

        $node = $marker;

        while ($node !== null) {
            $next = $node->nextSibling;
            $fragment->appendChild($node);
            $node = $next;
        }

        $this->trimTrailing($parent);

        [$headerText, $quotedHtml] = $this->unwrapLevel($fragment);

        [$quotedContent, $deeper] = $this->splitHtml($quotedHtml, $depth + 1);

        $quotes = [];
        $quotedText = $this->toText($quotedContent);

        if ($quotedText !== '') {
            $quotes[] = ['text' => $quotedText, ...$this->parseAttribution($headerText)];
        }

        return [$this->innerHtml($body), [...$quotes, ...$deeper]];
    }

    /**
     * One level of quoted history: its "On … wrote:" header, and the message
     * inside it.
     *
     * @return array{0: string, 1: string}
     */
    private function unwrapLevel(DOMElement $fragment): array
    {
        $doc = $fragment->ownerDocument;

        if (! $doc instanceof DOMDocument) {
            return ['', ''];
        }

        // Outlook: a header block (From/Sent/To/Subject) followed by the message.
        $found = (new DOMXPath($doc))->query(".//*[@id='divRplyFwdMsg']", $fragment);
        $outlook = $found === false ? null : $found->item(0);

        if ($outlook instanceof DOMElement) {
            $header = $this->toText($this->outerHtml($outlook));
            $outlook->parentNode?->removeChild($outlook);

            return [$header, $this->innerHtml($fragment)];
        }

        $blockquote = $fragment->getElementsByTagName('blockquote')->item(0);

        if ($blockquote instanceof DOMElement) {
            $inner = $this->innerHtml($blockquote);
            $blockquote->parentNode?->removeChild($blockquote);

            return [$this->toText($this->innerHtml($fragment)), $inner];
        }

        return ['', $this->innerHtml($fragment)];
    }

    private function precedingAttribution(DOMElement $marker): ?DOMNode
    {
        $node = $marker->previousSibling;

        while ($node !== null && ($node->nodeName === 'br' || trim((string) $node->textContent) === '')) {
            $node = $node->previousSibling;
        }

        if ($node === null) {
            return null;
        }

        $text = trim((string) $node->textContent);

        return mb_strlen($text) < 400 && preg_match(self::ATTRIBUTION, $text) === 1 ? $node : null;
    }

    /**
     * Drop the empty lines, <br>s and rules left dangling where the quote was cut.
     */
    private function trimTrailing(?DOMNode $parent): void
    {
        while ($parent !== null) {
            $last = $parent->lastChild;

            while ($last !== null && (in_array($last->nodeName, ['br', 'hr'], true)
                || ($last->nodeName !== 'img' && trim((string) $last->textContent) === ''))) {
                $parent->removeChild($last);
                $last = $parent->lastChild;
            }

            if ($parent->nodeName === 'body') {
                return;
            }

            $grand = $parent->parentNode;

            if ($parent->childNodes->length === 0) {
                $grand?->removeChild($parent);
            }

            $parent = $grand;
        }
    }

    /**
     * @return array{name: string|null, email: string|null, date: string|null, date_text: string|null}
     */
    private function parseAttribution(string $header): array
    {
        $header = trim((string) preg_replace('/\s+/u', ' ', $header));
        $empty = ['name' => null, 'email' => null, 'date' => null, 'date_text' => null];

        if ($header === '') {
            return $empty;
        }

        // Outlook: "From: Tom Bakker <tom@x.nl> Sent: Tuesday, 29 September 2026 14:12 To: … Subject: …"
        if (preg_match('/(?:From|Van|Von|De)\s*:\s*(.+?)\s+(?:Sent|Verzonden|Gesendet|Envoyé|Date|Datum)\s*:\s*(.+?)(?:\s+(?:To|Aan|An|À|Cc|Subject|Onderwerp|Betreff|Objet)\s*:|$)/iu', $header, $m) === 1) {
            return [...$this->nameAndEmail($m[1]), ...$this->date($m[2])];
        }

        // Dutch Gmail puts the verb first: "Op di 29 sep 2026 om 14:12 schreef Tom Bakker <t@x.nl>:"
        if (preg_match('/^Op\s+(.+?)\s+schreef\s+(.+?)\s*:?$/iu', $header, $m) === 1) {
            return [...$this->nameAndEmail($m[2]), ...$this->date($m[1])];
        }

        if (preg_match('/^(?:On|Am|Le|El|Il)\s+(.+?)\s*(?:wrote|schrieb|a écrit|escribió|ha scritto)\s*:?$/iu', $header, $m) !== 1) {
            return $empty;
        }

        $middle = $m[1];

        // "1 Oct 2026 at 13:24 +0200, Roger Meijer <r@x.nl>,"
        if (preg_match('/^(.*?),\s*([^,<]*?)\s*<([^>]+)>\s*,?$/iu', $middle, $parts) === 1) {
            return [
                'name' => trim($parts[2], " \"'") ?: null,
                'email' => mb_strtolower(trim($parts[3])),
                ...$this->date($parts[1]),
            ];
        }

        // No address: "On 29 Sep 2026, at 14:12, Tom Bakker wrote:"
        $pieces = array_map('trim', explode(',', $middle));
        $name = array_pop($pieces);

        return ['name' => $name !== '' ? $name : null, 'email' => null, ...$this->date(implode(', ', $pieces))];
    }

    /**
     * @return array{name: string|null, email: string|null}
     */
    private function nameAndEmail(string $value): array
    {
        if (preg_match('/^(.*?)\s*<([^>]+)>/u', $value, $m) === 1) {
            return ['name' => trim($m[1], " \"'") ?: null, 'email' => mb_strtolower(trim($m[2]))];
        }

        return str_contains($value, '@')
            ? ['name' => null, 'email' => mb_strtolower(trim($value))]
            : ['name' => trim($value) ?: null, 'email' => null];
    }

    /**
     * @return array{date: string|null, date_text: string|null}
     */
    private function date(string $value): array
    {
        $value = trim($value, " ,\t");

        if ($value === '') {
            return ['date' => null, 'date_text' => null];
        }

        $normalised = (string) preg_replace(['/\b(?:at|om|um|à)\b/iu', '/^\p{L}{2,9}\.?,?\s+(?=\d)/u'], [' ', ''], $value);

        try {
            $date = Carbon::parse($normalised)->utc()->toIso8601String();
        } catch (Throwable) {
            $date = null;
        }

        return ['date' => $date, 'date_text' => $value];
    }

    /**
     * @return array{0: string, 1: list<array{name: string|null, email: string|null, date: string|null, date_text: string|null, text: string}>}
     */
    private function splitText(string $text, int $depth): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $cut = null;

        foreach ($lines as $i => $line) {
            if (preg_match(self::ATTRIBUTION, $line) === 1
                || str_starts_with(ltrim($line), '>')
                || preg_match('/^\s*-{2,}\s*(?:Original Message|Oorspronkelijk bericht|Forwarded message|Doorgestuurd bericht)/iu', $line) === 1) {
                $cut = $i;

                break;
            }
        }

        if ($cut === null || $depth >= self::MAX_DEPTH) {
            return [$this->tidy(implode("\n", $lines)), []];
        }

        $content = $this->tidy(implode("\n", array_slice($lines, 0, $cut)));
        $rest = array_slice($lines, $cut);

        $header = preg_match(self::ATTRIBUTION, $rest[0]) === 1 ? array_shift($rest) : '';

        // One level of ">" down is the quoted message.
        $quoted = array_map(
            fn (string $line): string => (string) preg_replace('/^\s?>\s?/u', '', $line),
            $rest,
        );

        [$quotedContent, $deeper] = $this->splitText(implode("\n", $quoted), $depth + 1);

        $quotes = $quotedContent === '' ? [] : [['text' => $quotedContent, ...$this->parseAttribution($header)]];

        return [$content, [...$quotes, ...$deeper]];
    }

    /**
     * Readable plain text from HTML: blocks and <br> become line breaks.
     */
    private function toText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = $this->load($html);
        $text = $this->textOf($this->body($doc));

        return $this->tidy(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
    }

    private function textOf(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return (string) preg_replace('/\s+/u', ' ', (string) $node->nodeValue);
        }

        if ($node->nodeName === 'br') {
            return "\n";
        }

        if (in_array($node->nodeName, ['script', 'style', 'head', 'title'], true)) {
            return '';
        }

        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= $this->textOf($child);
        }

        if ($node->nodeName === 'li') {
            return "\n• ".trim($text)."\n";
        }

        return in_array($node->nodeName, self::BLOCK_ELEMENTS, true) ? "\n".$text."\n" : $text;
    }

    private function tidy(string $text): string
    {
        $lines = array_map(fn (string $line): string => trim($line), preg_split('/\R/u', $text) ?: []);

        $text = (string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

        // List items are blocks of their own; keep them on consecutive lines.
        return trim((string) preg_replace("/^(• .*)\n\n(?=• )/mu", "$1\n", $text));
    }

    private function load(string $html): DOMDocument
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // The parser already decoded the body to UTF-8, so a charset the
        // markup still declares (Outlook writes Windows-1252) is stale, and
        // libxml would believe it over the XML declaration: drop it.
        $html = (string) preg_replace('/<meta\b[^>]*\bcharset\s*=[^>]*>/i', '', $html);

        // The XML declaration makes libxml read the markup as UTF-8.
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }

    private function body(DOMDocument $doc): DOMNode
    {
        return $doc->getElementsByTagName('body')->item(0) ?? $doc->documentElement ?? $doc;
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child);
        }

        return $html;
    }

    private function outerHtml(DOMNode $node): string
    {
        return (string) $node->ownerDocument?->saveHTML($node);
    }
}
