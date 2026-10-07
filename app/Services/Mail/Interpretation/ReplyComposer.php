<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Models\EmailInterpretation;
use Carbon\CarbonInterface;
use Illuminate\Support\Traits\Localizable;

/**
 * The replies Howy sends, as HTML (resources/views/mail/cc) and as plain text.
 * The model writes the answer or the explanation of a conflict; the framing
 * around it is ours, in the mail's language when we ship that language, else
 * the account's.
 *
 * @phpstan-import-type Citation from EmailInterpretation
 * @phpstan-import-type Statement from EmailInterpretation
 */
class ReplyComposer
{
    use Localizable;

    /**
     * The wordmark, embedded in every mail: mail clients load no web fonts and
     * show no SVG, and an embedded image shows even where remote images are
     * blocked. Rendered at 3× from Plus Jakarta Sans 800; shown at 83×27.
     */
    private const string LOGO = 'howy-logo.png';

    /**
     * An answer to the sender, from the knowledge base.
     *
     * @param  list<Citation>  $citations
     */
    public function answer(string $question, string $answer, array $citations, ?string $gaps, string $locale): ComposedReply
    {
        return $this->withLocale($locale, function () use ($question, $answer, $citations, $gaps): ComposedReply {
            $lines = [$this->line(__('You asked: ":question"', ['question' => trim($question)])), '', trim($answer)];

            if ($gaps !== null) {
                $lines[] = '';
                $lines[] = $this->line(__('Not in the knowledge base yet: :gaps', ['gaps' => $gaps]));
            }

            array_push($lines, ...$this->sourceLines($citations));
            $lines[] = '';
            $lines[] = $this->line(__('Is something not right, or do you know more? Just reply to this email.'));

            return $this->compose('mail.cc.answer', $lines, [
                'question' => trim($question),
                'answer' => trim($answer),
                'gaps' => $gaps,
                'sources' => $this->sources($citations),
            ]);
        });
    }

    /**
     * A suggested answer for the people a question was put to. It goes to
     * them alone, so it says so, and that their own answer will be filed.
     *
     * @param  list<Citation>  $citations
     */
    public function suggestion(string $asker, ?CarbonInterface $askedAt, string $question, string $answer, array $citations, ?string $gaps, float $confidence, string $locale): ComposedReply
    {
        return $this->withLocale($locale, function () use ($asker, $askedAt, $question, $answer, $citations, $gaps, $confidence, $locale): ComposedReply {
            $lines = [
                $this->line(__(':name asked:', ['name' => $asker])),
                '"'.trim($question).'"',
                '',
                $this->line(__('A possible answer from the knowledge base:')),
                '',
                trim($answer),
            ];

            if ($gaps !== null) {
                $lines[] = '';
                $lines[] = $this->line(__('Not in the knowledge base yet: :gaps', ['gaps' => $gaps]));
                $lines[] = $this->line(__('Do you know? Put it in your answer to :name, and the knowledge base learns it too.', ['name' => $asker]));
            }

            array_push($lines, ...$this->sourceLines($citations));
            $lines[] = '';
            $lines[] = $this->line(__('This suggestion was sent only to you, not to :name. When you answer, your answer is taken into the knowledge base.', ['name' => $asker]));

            return $this->compose('mail.cc.suggestion', $lines, [
                'asker' => $asker,
                'initials' => $this->initials($asker),
                'askedAt' => $askedAt?->copy()->timezone(Tenancy::account()->timezone)->locale($locale)->isoFormat('D MMMM, HH:mm'),
                'question' => trim($question),
                'answer' => trim($answer),
                'gaps' => $gaps,
                'sources' => $this->sources($citations),
                'confidence' => (int) round($confidence * 100),
            ]);
        });
    }

    /**
     * The sender asked something the knowledge base knows nothing about.
     */
    public function notFound(string $question, string $locale): ComposedReply
    {
        return $this->withLocale($locale, fn (): ComposedReply => $this->compose('mail.cc.not-found', [
            $this->line(__('The knowledge base does not know about this yet.')),
            '',
            $this->line(__('You asked: ":question"', ['question' => trim($question)])),
            '',
            $this->line(__('Your question is saved with this email, so someone can pick it up. Once the answer is in the knowledge base, Howy knows it next time.')),
        ], ['question' => trim($question)]));
    }

    /**
     * Information that contradicts the knowledge base: what was held for
     * approval, set against what the knowledge base says, and what was added.
     *
     * @param  list<Statement>  $statements
     * @param  array<int, Citation>  $sources  the source of each conflicting fact, by fact id
     */
    public function conflict(array $statements, array $sources, string $locale): ComposedReply
    {
        return $this->withLocale($locale, function () use ($statements, $sources): ComposedReply {
            $conflicts = array_values(array_filter($statements, fn (array $statement): bool => $statement['verdict'] === 'conflict'));
            $added = array_values(array_filter($statements, fn (array $statement): bool => $statement['verdict'] === 'new'));

            $heading = trans_choice('{1} Thank you. One point contradicts the knowledge base; that change is waiting for approval.|[2,*] Thank you. :count points contradict the knowledge base; those changes are waiting for approval.', count($conflicts));
            $addedLine = $added === [] ? null : trans_choice('{1} The other point from your email is in the knowledge base now.|[2,*] The other :count points from your email are in the knowledge base now.', count($added));

            $items = array_map(function (array $conflict) use ($sources): array {
                $source = $sources[(int) $conflict['existing_fact_id']] ?? null;

                return [
                    'statement' => $conflict['statement'],
                    'existing' => $conflict['existing_statement'],
                    'source' => $source !== null ? $this->source($source) : null,
                    'explanation' => filled($conflict['explanation']) ? trim((string) $conflict['explanation']) : null,
                ];
            }, $conflicts);

            $lines = [$heading];

            if ($addedLine !== null) {
                $lines[] = $addedLine;
            }

            foreach ($items as $index => $item) {
                $lines[] = '';
                $lines[] = ($index + 1).'. '.$this->line(__('You wrote: ":statement"', ['statement' => $item['statement']]));

                if ($item['existing'] !== null) {
                    $lines[] = '   '.$this->line(__('The knowledge base says: ":statement"', ['statement' => $item['existing']]))
                        .($item['source'] !== null ? ' ('.$item['source']['label'].')' : '');
                }

                if ($item['explanation'] !== null) {
                    $lines[] = '   '.$item['explanation'];
                }
            }

            $lines[] = '';
            $lines[] = $this->line(__('Your change is ready for approval in Howy. If it is approved, it replaces what the knowledge base says now. Until then, the current version stands.'));

            foreach ($added as $statement) {
                $lines[] = $this->line(__('Added: :statement', ['statement' => $statement['statement']]));
            }

            return $this->compose('mail.cc.conflict', $lines, [
                'heading' => $heading,
                'addedLine' => $addedLine,
                'conflicts' => $items,
                'added' => array_column($added, 'statement'),
            ]);
        });
    }

    /**
     * The locale of the reply: the mail's own language when we ship it.
     */
    public function locale(?string $language, Locale $fallback): string
    {
        return (Locale::tryFrom(strtolower((string) $language)) ?? $fallback)->value;
    }

    /**
     * @param  view-string  $view
     * @param  list<string>  $lines
     * @param  array<string, mixed>  $data
     */
    private function compose(string $view, array $lines, array $data): ComposedReply
    {
        $text = implode("\n", [...$lines, '', '-- ', $this->line(__('Howy knowledge base'))])."\n";

        return new ComposedReply($text, view($view, ['logo' => 'cid:'.self::LOGO, ...$data])->render(), [
            self::LOGO => resource_path('images/mail/howy-logo@3x.png'),
        ]);
    }

    /**
     * @param  list<Citation>  $citations
     * @return list<string>
     */
    private function sourceLines(array $citations): array
    {
        if ($citations === []) {
            return [];
        }

        return ['', $this->line(__('Sources:')), ...array_map(fn (array $source): string => '- '.$source['label'], $this->sources($citations))];
    }

    /**
     * @param  list<Citation>  $citations
     * @return list<array{title: string, detail: string|null, label: string}>
     */
    private function sources(array $citations): array
    {
        return array_map($this->source(...), $citations);
    }

    /**
     * A source as a title and a detail line ("§ 8. Beveiliging · p. 4").
     *
     * @param  Citation  $citation
     * @return array{title: string, detail: string|null, label: string}
     */
    private function source(array $citation): array
    {
        if ($citation['type'] === 'email') {
            $title = $this->line(__('email ":subject"', ['subject' => $citation['title'] !== '' ? $citation['title'] : $this->line(__('(no subject)'))]));

            return ['title' => $title, 'detail' => null, 'label' => $title];
        }

        $detail = implode(' · ', array_filter([
            $citation['heading_path'] !== null ? '§ '.$citation['heading_path'] : null,
            $citation['page'] !== null ? $this->line(__('p. :page', ['page' => $citation['page']])) : null,
        ])) ?: null;

        return [
            'title' => $citation['title'],
            'detail' => $detail,
            'label' => implode(', ', array_filter([$citation['title'], $detail])),
        ];
    }

    private function initials(string $name): string
    {
        $words = preg_split('/[\s@.]+/u', trim($name), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters) ?: '?';
    }

    /**
     * A translated line. JSON keys always translate to a string; only a group
     * key could return an array, and none is used here.
     *
     * @param  array<array-key, mixed>|string  $line
     */
    private function line(array|string $line): string
    {
        return is_string($line) ? $line : '';
    }
}
