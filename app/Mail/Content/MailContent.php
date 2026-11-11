<?php

namespace App\Mail\Content;

use App\Enums\SenderAccess;

/**
 * A mail Howy writes, as a list of blocks: one description that renders both
 * as the designed HTML (resources/views/mail/blocks) and as plain text, so the
 * two never drift apart. Each block keeps its data for its view and its plain
 * text, written when it is added. Text may mark **bold**; everything else is
 * escaped.
 *
 * @phpstan-type Block array{type: string, data: array<string, mixed>, text: string}
 */
final class MailContent
{
    /** @var list<Block> */
    private array $blocks = [];

    private ?string $footerReason = null;

    private ?string $unsubscribeUrl = null;

    /**
     * @param  string|null  $label  what kind of mail this is, top right ("Welcome")
     * @param  string|null  $account  the account the mail is about, after the wordmark
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $preheader = '',
        public readonly ?string $label = null,
        public readonly ?string $account = null,
    ) {}

    public static function make(string $subject, string $preheader = '', ?string $label = null, ?string $account = null): self
    {
        return new self($subject, $preheader, $label, $account);
    }

    /**
     * The opening line in large type; the highlight follows it on a marker.
     */
    public function heading(string $text, ?string $highlight = null): self
    {
        return $this->add('heading', ['text' => $text, 'highlight' => $highlight], trim($text.' '.$highlight));
    }

    public function subheading(string $text, ?string $eyebrow = null): self
    {
        return $this->add('subheading', ['text' => $text, 'eyebrow' => $eyebrow], $this->lines($eyebrow, [$text]));
    }

    public function paragraph(string $text, bool $muted = false): self
    {
        return $this->add('paragraph', ['text' => $text, 'muted' => $muted], $text);
    }

    /**
     * @param  list<string>  $items
     */
    public function list(array $items, ?string $title = null): self
    {
        return $items === [] ? $this : $this->add('list', ['items' => $items, 'title' => $title], $this->lines($title, $this->bullets($items)));
    }

    /**
     * Howy's two rules, Cc to save and To to ask, as far as this access allows.
     */
    public function rules(SenderAccess $access, string $address): self
    {
        $lines = [];

        if ($access->learns()) {
            $lines[] = __('Cc = save knowledge. Copy me in (:address) and I keep what matters.', ['address' => $address]);
        }

        if ($access->answers()) {
            $lines[] = __('To = find knowledge. Email me (:address) your question and you get an answer.', ['address' => $address]);
        }

        return $this->add('rules', ['learns' => $access->learns(), 'answers' => $access->answers(), 'address' => $address], $this->lines(null, $lines));
    }

    /**
     * What Howy does not do for this person, when it does not do both.
     */
    public function status(SenderAccess $access, string $account): self
    {
        $text = match ($access) {
            SenderAccess::Learn => __('**Good to know:** I learn from the emails you copy me in on, but I don\'t answer your questions. Want that changed? Ask an admin at :account.', ['account' => $account]),
            SenderAccess::Answer => __('**Good to know:** I answer your questions, but I don\'t keep anything from your emails. Want that changed? Ask an admin at :account.', ['account' => $account]),
            default => null,
        };

        return $text === null ? $this : $this->callout($text, tone: 'note');
    }

    /**
     * A box set apart from the text: a panel, a dashed lime outline, or a
     * tinted note.
     *
     * @param  'panel'|'dashed'|'note'|'warn'  $tone
     * @param  list<string>  $items
     */
    public function callout(string $text, ?string $title = null, string $tone = 'panel', array $items = []): self
    {
        return $this->add('callout', ['text' => $text, 'title' => $title, 'tone' => $tone, 'items' => $items], $this->lines($title, [...($text !== '' ? [$text] : []), ...$this->bullets($items)]));
    }

    public function button(string $label, string $url): self
    {
        $target = str_starts_with($url, 'mailto:') ? (string) strtok(substr($url, 7), '?') : $url;

        return $this->add('button', ['label' => $label, 'url' => $url], $label.': '.$target);
    }

    public function code(string $code): self
    {
        return $this->add('code', ['code' => $code], $code);
    }

    /**
     * A sketch of an email: header rows (label → value), the marked one
     * highlighted, then a line of body text and Howy's reply under it.
     *
     * @param  list<array{label: string, value: string, marked?: bool}>  $rows
     */
    public function mockup(array $rows, ?string $body = null, ?string $reply = null): self
    {
        $lines = array_map(fn (array $row): string => $row['label'].': '.$row['value'], $rows);

        return $this->add('mockup', ['rows' => $rows, 'body' => $body, 'reply' => $reply], $this->lines(null, [...$lines, ...array_values(array_filter([$body, $reply]))]));
    }

    /**
     * Figures side by side, each with a line saying what it counts.
     *
     * @param  list<array{value: string, label: string}>  $stats
     */
    public function stats(array $stats): self
    {
        return $this->add('stats', ['stats' => $stats], $this->lines(null, array_map(fn (array $stat): string => $stat['value'].' '.$stat['label'], $stats)));
    }

    /**
     * A numbered top list: a title, what it is about, and a figure.
     *
     * @param  list<array{title: string, meta?: string|null, value?: string|null}>  $rows
     */
    public function ranking(array $rows, ?string $title = null): self
    {
        $lines = array_map(
            fn (array $row, int $index): string => ($index + 1).'. '.implode(' · ', array_filter([$row['title'], $row['meta'] ?? null, $row['value'] ?? null])),
            $rows,
            array_keys($rows),
        );

        return $this->add('ranking', ['rows' => $rows, 'title' => $title], $this->lines($title, $lines));
    }

    /**
     * @param  list<array{text: string, value?: string|null}>  $items
     */
    public function checklist(array $items, ?string $title = null): self
    {
        $lines = array_map(fn (array $item): string => '[ ] '.$item['text'].(($item['value'] ?? null) !== null ? ' ('.$item['value'].')' : ''), $items);

        return $this->add('checklist', ['items' => $items, 'title' => $title], $this->lines($title, $lines));
    }

    public function quote(string $label, string $text): self
    {
        return $this->add('quote', ['label' => $label, 'text' => $text], $label.":\n\"".$text.'"');
    }

    /**
     * Two to four answers to one question, each a link: the way to say
     * something back in one click.
     *
     * @param  list<array{label: string, url: string}>  $choices
     */
    public function choices(string $question, array $choices, ?string $note = null): self
    {
        $lines = array_map(fn (array $choice): string => '- '.$choice['label'].': '.$choice['url'], $choices);

        return $this->add('choices', ['question' => $question, 'choices' => $choices, 'note' => $note], $this->lines($question, [...$lines, ...($note !== null ? ['', $note] : [])]));
    }

    /**
     * How Howy signs off: a greeting, its name, and what it is for whom.
     */
    public function signoff(string $greeting, ?string $line = null): self
    {
        return $this->add('signoff', ['greeting' => $greeting, 'line' => $line], $this->lines($greeting, ['Howy', ...($line !== null ? [$line] : [])]));
    }

    /**
     * Why the reader got this mail, under the card, with the way out when
     * there is one.
     */
    public function footer(string $reason, ?string $unsubscribeUrl = null): self
    {
        $this->footerReason = $reason;
        $this->unsubscribeUrl = $unsubscribeUrl;

        return $this;
    }

    /**
     * @return list<Block>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    public function footerReason(): ?string
    {
        return $this->footerReason;
    }

    public function unsubscribeUrl(): ?string
    {
        return $this->unsubscribeUrl;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function add(string $type, array $data, string $text): self
    {
        $this->blocks[] = ['type' => $type, 'data' => $data, 'text' => $text];

        return $this;
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function bullets(array $items): array
    {
        return array_map(fn (string $item): string => '- '.$item, $items);
    }

    /**
     * @param  list<string>  $lines
     */
    private function lines(?string $title, array $lines): string
    {
        return implode("\n", $title !== null ? [$title, ...$lines] : $lines);
    }
}
