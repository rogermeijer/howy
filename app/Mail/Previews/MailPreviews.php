<?php

namespace App\Mail\Previews;

use App\Enums\SenderAccess;
use App\Mail\Content\MailContent;
use App\Mail\Content\MailRenderer;
use App\Mail\Content\RenderedMail;
use App\Mail\Flow\FlowMails;
use App\Mail\Flow\FlowRecipient;
use App\Mail\Parked\ParkedMails;
use App\Mail\SystemMails;
use App\Services\Mail\Interpretation\ComposedReply;
use App\Services\Mail\Interpretation\ReplyComposer;
use Closure;
use Illuminate\Support\Traits\Localizable;

/**
 * Every mail Howy sends or will send, rendered with sample data for
 * /admin/mails: the flow mails, the replies, the system mails and the parked
 * ones. Sample data only, so no account's data is involved.
 *
 * @phpstan-type Preview array{key: string, group: string, title: string, access: bool, render: Closure(SenderAccess): RenderedMail}
 */
class MailPreviews
{
    use Localizable;

    private const string ACCOUNT = 'Noordkade';

    private const string ADDRESS = 'howy@noordkade.nl';

    public function __construct(
        private readonly MailRenderer $renderer,
        private readonly FlowMails $flow,
        private readonly SystemMails $system,
        private readonly ParkedMails $parked,
        private readonly ReplyComposer $replies,
    ) {}

    /**
     * The previews in the order /admin/mails lists them, without rendering.
     *
     * @return list<array{key: string, group: string, title: string, access: bool}>
     */
    public function list(): array
    {
        return array_map(fn (array $preview): array => [
            'key' => $preview['key'],
            'group' => $preview['group'],
            'title' => $preview['title'],
            'access' => $preview['access'],
        ], $this->previews());
    }

    public function has(string $key): bool
    {
        return $this->find($key) !== null;
    }

    public function render(string $key, string $locale, SenderAccess $access = SenderAccess::LearnAndAnswer): ?RenderedMail
    {
        $preview = $this->find($key);

        return $preview === null ? null : $this->withLocale($locale, fn (): RenderedMail => ($preview['render'])($access));
    }

    /**
     * @return Preview|null
     */
    private function find(string $key): ?array
    {
        foreach ($this->previews() as $preview) {
            if ($preview['key'] === $key) {
                return $preview;
            }
        }

        return null;
    }

    /**
     * @return list<Preview>
     */
    private function previews(): array
    {
        $link = route('home');

        return [
            $this->content('welcome', 'flow', '01 · Welcome', fn (SenderAccess $access): MailContent => $this->flow->welcome($this->recipient($access))),
            $this->content('how-it-works', 'flow', '02 · How it works', fn (SenderAccess $access): MailContent => $this->flow->howItWorks($this->recipient($access))),
            $this->content('first-save', 'flow', '04 · Saved!', fn (SenderAccess $access): MailContent => $this->flow->firstSave($this->recipient($access), 'Delivery agreements 2027', [
                'Delivery to Noordkade is within 5 working days of the order.',
                'The payment term for supplier invoices is 30 days.',
                'Price changes are announced at least two months ahead.',
                'Returns are collected on Tuesdays.',
            ], true, $link)),
            $this->content('nudge', 'flow', '06 · What do you know?', fn (SenderAccess $access): MailContent => $this->flow->nudge($this->recipient($access))),
            $this->content('new-link', 'flow', 'New link', fn (SenderAccess $access): MailContent => $this->flow->newLink($this->recipient($access), $link, (int) config('adoption.mail_link_hours', 48)), access: false),

            $this->reply('answer', '05 · Answer', fn (): RenderedMail => $this->composed($this->replies->answer(
                'How many days of leave do I get?',
                "You get 25 days of leave a year, on a full-time contract. Part-timers get the same share of their hours.\n\nUnused days carry over to the next year once.",
                [$this->citation('Staff handbook', 'Leave', 4)],
                null,
                app()->getLocale(),
                ['valuable' => $link, 'partly' => $link, 'outdated' => $link],
            ), __('Re: :subject', ['subject' => 'Leave']))),
            $this->reply('not-found', 'Not found', fn (): RenderedMail => $this->composed($this->replies->notFound('What is the warranty on custom work?', app()->getLocale()), __('Re: :subject', ['subject' => 'Warranty']))),
            $this->reply('conflict', 'Conflict', fn (): RenderedMail => $this->composed($this->replies->conflict([
                [
                    'statement' => 'Returns are collected on Thursdays from now on.',
                    'verdict' => 'conflict',
                    'existing_fact_id' => 1,
                    'existing_statement' => 'Returns are collected on Tuesdays.',
                    'explanation' => 'The day changes from Tuesday to Thursday.',
                    'review' => 'pending',
                    'fact_id' => null,
                    'subject' => null,
                    'valid_from' => null,
                    'flag' => null,
                    'from_history' => false,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ],
            ], [1 => $this->citation('Delivery agreements 2027', null, null)], app()->getLocale()), __('Re: :subject', ['subject' => 'Returns']))),

            $this->content('beta-invite', 'system', 'Beta invite', fn (): MailContent => $this->system->betaInvite('K7QF-2M9X', $link), access: false),
            $this->content('mailbox-needs-reconnecting', 'system', 'Mailbox needs reconnecting', fn (): MailContent => $this->system->mailboxNeedsReconnecting(self::ADDRESS, self::ACCOUNT, $link), access: false),
            $this->content('reset-password', 'system', 'Reset password', fn (): MailContent => $this->system->resetPassword('Sanne de Vries', 'sanne@noordkade.nl', $link, 60), access: false),
            $this->content('verify-email', 'system', 'Verify email', fn (): MailContent => $this->system->verifyEmail('Sanne de Vries', 'sanne@noordkade.nl', $link), access: false),

            $this->content('welcome-challenge', 'parked', '03 · Welcome challenge', fn (SenderAccess $access): MailContent => $this->parked->welcomeChallenge($this->recipient($access), '21 November', $link)),
            $this->content('weekly-topics', 'parked', '07 · Trending topics', fn (SenderAccess $access): MailContent => $this->parked->weeklyTopics($this->recipient($access), '46', [
                ['title' => 'Supplier agreements 2027', 'meta' => '14 new items · asked 22×', 'value' => '↑ 180%'],
                ['title' => 'Onboarding new customers', 'meta' => '9 new items · asked 15×', 'value' => '↑ 60%'],
                ['title' => 'Planning Q1', 'meta' => '7 new items · asked 11×', 'value' => '↑ 40%'],
            ], [
                ['value' => '6', 'label' => 'items shared'],
                ['value' => '3', 'label' => 'weeks in a row'],
                ['value' => '+45', 'label' => 'credits earned'],
            ], $link)),
            $this->content('challenge-completed', 'parked', '08 · Challenge completed', fn (SenderAccess $access): MailContent => $this->parked->challengeCompleted($this->recipient($access), 12, 250, $link)),
            $this->content('howy-asks', 'parked', '09 · Howy asks', fn (SenderAccess $access): MailContent => $this->parked->howyAsks($this->recipient($access), 'supplier agreements', 'A colleague asked yesterday and I couldn\'t find it.', 'Which discount applies to repeat customers?', $link, $link)),
            $this->content('knowledge-helped', 'parked', '10 · Your knowledge helped', fn (SenderAccess $access): MailContent => $this->parked->knowledgeHelped($this->recipient($access), 'Mo', 'What is the payment term for our supplier?', 'Delivery agreements 2027', 'Found it in ten seconds, thanks!', [
                ['value' => '9×', 'label' => 'your knowledge was used'],
                ['value' => '≈ 4 h', 'label' => 'search time saved'],
                ['value' => '+10', 'label' => 'credits for this rating'],
            ], $link)),
            $this->content('monthly-update', 'parked', '11 · Monthly update', fn (SenderAccess $access): MailContent => $this->parked->monthlyUpdate($this->recipient($access), 'October', [
                ['value' => '142', 'label' => 'new knowledge items'],
                ['value' => '31', 'label' => 'colleagues shared knowledge'],
                ['value' => '84%', 'label' => 'of answers rated valuable'],
            ], [
                ['title' => 'Supplier agreements 2027', 'meta' => '38 items · reused 61×', 'value' => '94'],
                ['title' => 'Onboarding new customers', 'meta' => '24 items · reused 40×', 'value' => '81'],
                ['title' => 'Planning Q1', 'meta' => '19 items · reused 27×', 'value' => '66'],
            ], [
                ['value' => '18', 'label' => 'items shared'],
                ['value' => '47×', 'label' => 'reused by colleagues'],
                ['value' => '+210', 'label' => 'credits'],
            ], ['Warranty on custom work', 'Procedure for weekend outages', 'Agreements with the new cleaning company'], 'Sanne (Purchasing) · Mo (Customer service) · Lisa (Planning)', $link)),
            $this->content('new-colleague', 'parked', '12 · New colleague', fn (SenderAccess $access): MailContent => $this->parked->newColleague($this->recipient($access), 'planner', 'Operations', 'Lisa', [
                'How do I request leave?',
                'Where do I find the house style?',
                'Who knows most about our largest customer?',
                'How does the quotation process work?',
            ], $link)),
            $this->content('team-report', 'parked', '13 · Team report', fn (SenderAccess $access): MailContent => $this->parked->teamReport($this->recipient($access), 'Operations', 'October', [
                ['value' => '64%', 'label' => 'of the team shared knowledge (↑ 9%)'],
                ['value' => '58', 'label' => 'new knowledge items (↑ 12%)'],
                ['value' => '41', 'label' => 'questions asked (↑ 18%)'],
            ], [
                ['value' => '82%', 'label' => 'of answers rated valuable'],
                ['value' => '≈ 31 h', 'label' => 'search time saved'],
                ['value' => '1', 'label' => 'new colleague onboarded'],
            ], ['Which discounts apply to repeat customers?', 'How do we cover for the planner when they are ill?'], $link)),
        ];
    }

    private function recipient(SenderAccess $access): FlowRecipient
    {
        return new FlowRecipient('Sanne de Vries', $access, self::ACCOUNT, self::ADDRESS, route('home'));
    }

    /**
     * @param  Closure(SenderAccess): MailContent  $build
     * @return Preview
     */
    private function content(string $key, string $group, string $title, Closure $build, bool $access = true): array
    {
        return [
            'key' => $key,
            'group' => $group,
            'title' => $title,
            'access' => $access,
            'render' => fn (SenderAccess $as): RenderedMail => $this->renderer->render($build($as)),
        ];
    }

    /**
     * @param  Closure(): RenderedMail  $render
     * @return Preview
     */
    private function reply(string $key, string $title, Closure $render): array
    {
        return ['key' => $key, 'group' => 'reply', 'title' => $title, 'access' => false, 'render' => fn (SenderAccess $as): RenderedMail => $render()];
    }

    private function composed(ComposedReply $reply, string $subject): RenderedMail
    {
        return new RenderedMail($subject, $reply->html, $reply->text);
    }

    /**
     * @return array{type: 'document', id: int, title: string, section_id: null, heading_path: string|null, page: int|null}
     */
    private function citation(string $title, ?string $heading, ?int $page): array
    {
        return ['type' => 'document', 'id' => 1, 'title' => $title, 'section_id' => null, 'heading_path' => $heading, 'page' => $page];
    }
}
