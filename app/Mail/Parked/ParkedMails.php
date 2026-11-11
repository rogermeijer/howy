<?php

namespace App\Mail\Parked;

use App\Mail\Content\MailContent;
use App\Mail\Flow\FlowRecipient;

/**
 * The mails from the adoption proposal that are designed but not sent yet:
 * nothing triggers them, and some name things Howy does not have (credits,
 * badges, teams, a knowledge holder per topic). They live here, in the same
 * frame as the rest, so picking one up starts from a finished design.
 * /admin/mails shows them with sample data; docs/adoption-mails.md says per
 * mail what is missing before it can go out.
 */
class ParkedMails
{
    /**
     * 03: four small actions in seven days, to become a Howy Hero.
     */
    public function welcomeChallenge(FlowRecipient $to, string $deadline, string $progressUrl): MailContent
    {
        return $this->close(MailContent::make(
            __('Your welcome challenge: 4 small actions, 7 days'),
            __('Together we build the memory of :account from day one.', ['account' => $to->account]),
            __('Getting started · 3/3'),
            $to->account,
        )
            ->heading(__('Are you in?'), __('Become a Howy Hero.'))
            ->paragraph($this->greeting($to))
            ->paragraph(__('You know who I am and how it works. Time to try it together. The colleagues who are there from the start lay the foundation everyone at :account builds on. That\'s why we call them **Howy Heroes**. Four small actions, seven days. Altogether a quarter of an hour at most.', ['account' => $to->account]))
            ->checklist([
                ['text' => __('**Copy me in 3 times** on an email with an agreement, decision or way of working.'), 'value' => '+30'],
                ['text' => __('**Ask me your first question.** Just an email, with me in the To field.'), 'value' => '+10'],
                ['text' => __('**Tell me what you\'re good at.** Pick a few topics, so colleagues know who to ask.'), 'value' => '+20'],
                ['text' => __('**Read how I handle your data.** The privacy policy, three minutes.'), 'value' => '+10'],
            ], __('Your challenge · until :date', ['date' => $deadline]))
            ->callout(__('All done? Then you get 70 credits and the "Howy Hero" badge. You save credits for rewards :account picks.', ['account' => $to->account]), tone: 'note')
            ->paragraph(__('It\'s not about as many emails as possible. It\'s about knowledge a colleague really gets something out of later. Quality counts, not quantity.'), muted: true)
            ->button(__('See your progress'), $progressUrl), $to, __('Good luck!'));
    }

    /**
     * 07: every Friday, the topics people shared and looked up most.
     *
     * @param  list<array{title: string, meta?: string|null, value?: string|null}>  $topics
     * @param  list<array{value: string, label: string}>  $mine
     */
    public function weeklyTopics(FlowRecipient $to, string $week, array $topics, array $mine, string $topicsUrl): MailContent
    {
        return $this->close(MailContent::make(
            __('Trending knowledge topics at :account this week', ['account' => $to->account]),
            __('What you shared most knowledge about this week, and what colleagues are still looking for.'),
            __('Week :week', ['week' => $week]),
            $to->account,
        )
            ->heading(__('Trending knowledge topics'), __('this week'))
            ->paragraph($this->greeting($to))
            ->paragraph(__('This is what you shared and looked up most this week. What\'s going on at :account, at a glance.', ['account' => $to->account]))
            ->ranking($topics, __('Top 3 this week'))
            ->subheading(__('Your week'))
            ->stats($mine)
            ->button(__('See all topics'), $topicsUrl), $to, __('Have a good weekend!'));
    }

    /**
     * 08: the welcome challenge is done.
     */
    public function challengeCompleted(FlowRecipient $to, int $heroes, int $credits, string $badgeUrl): MailContent
    {
        $name = $to->firstName();

        return $this->close(MailContent::make(
            __('Done! You\'re a Howy Hero of :account', ['account' => $to->account]),
            __('Your 70 credits and badge are waiting. Thank you for getting started.'),
            __('Challenge completed'),
            $to->account,
        )
            ->heading($name !== null ? __('You did it, :name!', ['name' => $name]) : __('You did it!'))
            ->paragraph(__('You finished the welcome challenge. That makes you one of the :count Howy Heroes of :account: the colleagues building the company\'s memory from day one.', ['count' => $heroes, 'account' => $to->account]))
            ->stats([
                ['value' => '+70', 'label' => __('credits added')],
                ['value' => (string) $credits, 'label' => __('credits in total')],
                ['value' => '1', 'label' => __('badge earned')],
            ])
            ->list([
                __('You shared knowledge colleagues can now find themselves.'),
                __('You asked your first question and know how fast it works.'),
                __('Colleagues now know what you\'re good at.'),
            ], __('What you achieved in one week'))
            ->callout(__('Just keep going. Copy me in when it matters and ask me what you want to know. Every Friday, Trending knowledge topics shows what\'s going on, and how long your streak is.'), __('What now?'))
            ->button(__('See your badge and credits'), $badgeUrl), $to, __('Thank you for being there from the start. Regards,'));
    }

    /**
     * 09: a knowledge gap, put to the person who probably knows.
     */
    public function howyAsks(FlowRecipient $to, string $topic, string $reason, string $question, string $someoneElseUrl, string $notNowUrl): MailContent
    {
        $name = $to->firstName();

        return $this->close(MailContent::make(
            $name !== null ? __(':name, may I ask you something about :topic?', ['name' => $name, 'topic' => $topic]) : __('May I ask you something about :topic?', ['topic' => $topic]),
            __('A colleague is looking for the answer. You probably know it. Takes about 2 minutes.'),
            __('Howy asks'),
            $to->account,
        )
            ->paragraph($this->greeting($to))
            ->paragraph(__('I saw you shared a lot about **:topic** before. You clearly know your way around it, so I\'m coming to you. :reason Could you help me with this question?', ['topic' => $topic, 'reason' => $reason]))
            ->quote(__('The question'), $question)
            ->paragraph(__('Just reply to this email. A few sentences is enough; I\'ll turn it into proper knowledge. Takes about 2 minutes.'))
            ->callout(__('+25 credits, and the colleague who asked hears from me right away.'), tone: 'note')
            ->choices(__('Not for you?'), [
                ['label' => __('Someone else knows better'), 'url' => $someoneElseUrl],
                ['label' => __('Not now'), 'url' => $notNowUrl],
            ], __('I ask you a question a few times a month at most. Skipping is always fine.')), $to, __('Thanks in advance,'));
    }

    /**
     * 10: a colleague rated knowledge from this person as valuable.
     *
     * @param  list<array{value: string, label: string}>  $impact
     */
    public function knowledgeHelped(FlowRecipient $to, string $colleague, string $question, string $source, string $thanks, array $impact, string $impactUrl): MailContent
    {
        return $this->close(MailContent::make(
            __('Your knowledge helped :name along', ['name' => $colleague]),
            __(':name found your answer valuable. That\'s how you make a difference.', ['name' => $colleague]),
            __('Your impact'),
            $to->account,
        )
            ->heading(__('Your knowledge'), __('made the difference.'))
            ->paragraph($this->greeting($to))
            ->paragraph(__('A colleague had a question. The answer came from you, even if you weren\'t there.'))
            ->quote(__(':name asked', ['name' => $colleague]), $question)
            ->callout(__('Answer from your email ":source" · rated valuable', ['source' => $source]), tone: 'note')
            ->quote(__('Thanks from :name', ['name' => $colleague]), $thanks)
            ->subheading(__('Your impact this month'))
            ->stats($impact)
            ->paragraph(__('Quality counts. You get credits when colleagues find your knowledge valuable, not for the number of emails you share.'), muted: true)
            ->button(__('See your impact'), $impactUrl), $to, __('This is exactly why it works: what you know helps others along. Regards,'));
    }

    /**
     * 11: the first working day of the month, what everyone built together.
     *
     * @param  list<array{value: string, label: string}>  $stats
     * @param  list<array{title: string, meta?: string|null, value?: string|null}>  $topics
     * @param  list<array{value: string, label: string}>  $mine
     * @param  list<string>  $gaps
     */
    public function monthlyUpdate(FlowRecipient $to, string $month, array $stats, array $topics, array $mine, array $gaps, ?string $contributors, string $overviewUrl): MailContent
    {
        $mail = MailContent::make(
            __('Monthly update :month: what we learned together', ['month' => $month]),
            __('New knowledge, the top 3 topics and your contribution.'),
            __('Monthly update :month', ['month' => $month]),
            $to->account,
        )
            ->heading(__('This is what we learned together'), __('in :month.', ['month' => $month]))
            ->paragraph($this->greeting($to))
            ->paragraph(__('Every month the memory of :account grows. This is what you built together.', ['account' => $to->account]))
            ->stats($stats)
            ->ranking($topics, __('Most valuable topics this month'))
            ->subheading(__('Your contribution'))
            ->stats($mine)
            ->callout(__('Know something about it? Copy me in on your next email about it.'), __('We know too little about this yet'), 'dashed', $gaps);

        if ($contributors !== null) {
            $mail->callout($contributors, __('Contributors of the month'))
                ->paragraph(__('Only colleagues who chose to are listed here.'), muted: true);
        }

        return $this->close($mail->button(__('See the full overview'), $overviewUrl), $to, __('Thank you for everything you shared. On to a new month. Regards,'));
    }

    /**
     * 12: the first working day of a new colleague, with knowledge ready.
     *
     * @param  list<string>  $starters
     */
    public function newColleague(FlowRecipient $to, string $role, string $team, string $buddy, array $starters, string $starterUrl): MailContent
    {
        $name = $to->firstName();

        return $this->close(MailContent::make(
            $name !== null ? __('Welcome to :account, :name! Everything you need to know, in one place', ['account' => $to->account, 'name' => $name]) : __('Welcome to :account! Everything you need to know, in one place', ['account' => $to->account]),
            __('You don\'t have to remember everything at once. That\'s what I\'m for.'),
            __('Welcome!'),
            $to->account,
        )
            ->heading($name !== null ? __('Welcome, :name!', ['name' => $name]) : __('Welcome!'), __('You don\'t have to remember everything at once.'))
            ->paragraph(__('Great to have you. I\'m Howy, the knowledge base of :account. Everything your colleagues shared about how we work, what we decided and what we agree with customers, you can find with me.', ['account' => $to->account]))
            ->list([
                __('**How we work here:** the most important ways of working'),
                __('**Tools and accounts:** where to find what'),
                __('**Who\'s who in team :team**', ['team' => $team]),
                __('**Our main customers and agreements**'),
            ], __('Ready for you as :role', ['role' => $role]))
            ->list($starters, __('Ask me anything. Really anything.'))
            ->paragraph(__('Email me your question, with me in the To field: **:address**. There are no stupid questions, and I don\'t mind explaining something ten times.', ['address' => $to->address]))
            ->callout(__('**Colleagues stay your most important source.** Your buddy :buddy and team :team are happy to help. I make sure you don\'t have to interrupt them for everything.', ['buddy' => $buddy, 'team' => $team]), tone: 'dashed')
            ->rules($to->access, $to->address)
            ->button(__('Ask your first question'), 'mailto:'.$to->address)
            ->button(__('See your starter pack'), $starterUrl), $to, __('Have a great first week!'));
    }

    /**
     * 13: the monthly report for a team lead, team figures only.
     *
     * @param  list<array{value: string, label: string}>  $stats
     * @param  list<array{value: string, label: string}>  $more
     * @param  list<string>  $openQuestions
     */
    public function teamReport(FlowRecipient $to, string $team, string $month, array $stats, array $more, array $openQuestions, string $dashboardUrl): MailContent
    {
        return $this->close(MailContent::make(
            __('Team report :team · :month: how your team uses Howy', ['team' => $team, 'month' => $month]),
            __('Use, growth, knowledge risks and three things you can do this month.'),
            __('Team report · :month', ['month' => $month]),
            $to->account,
        )
            ->heading(__('Team report :team', ['team' => $team]), $month)
            ->paragraph($this->greeting($to))
            ->paragraph(__('Your monthly overview of how team :team uses Howy. In two minutes you see what goes well and where your team still leaves knowledge on the table. All figures are about the team as a whole.', ['team' => $team]))
            ->stats($stats)
            ->stats($more)
            ->list($openQuestions, __('Questions from your team without an answer'))
            ->list([
                __('**Take 5 minutes in your team meeting.** Go through the open questions above and agree who answers them.'),
                __('**Name what goes well.** Thank the team for the growth. A compliment works better than a reminder.'),
                __('**Set the example.** Copy Howy in on decisions you make as a manager. Your team will follow.'),
            ], __('What can you do this month?'))
            ->callout(__('This report only shows figures for the team as a whole.'), __('Privacy'))
            ->button(__('See the team dashboard'), $dashboardUrl), $to, __('Until next month,'));
    }

    private function close(MailContent $mail, FlowRecipient $to, string $greeting): MailContent
    {
        return $mail->signoff($greeting, __('The knowledge base of :account · :address', ['account' => $to->account, 'address' => $to->address]))
            ->footer(__('You get this email because :account works with Howy.', ['account' => $to->account]), $to->unsubscribeUrl);
    }

    private function greeting(FlowRecipient $to): string
    {
        $name = $to->firstName();

        return $name !== null ? __('Hi :name,', ['name' => $name]) : __('Hi,');
    }
}
