<?php

namespace App\Mail\Flow;

use App\Enums\SenderAccess;
use App\Mail\Content\MailContent;

/**
 * The mails that help a colleague start with Howy: the welcome (01), how it
 * works (02), the first contribution (04), the nudge (06), and a new link
 * for a page that expired. Each says only what this person's access allows:
 * someone Howy only learns from is never told to ask it something.
 *
 * Callers set the locale; see docs/adoption-mails.md for when each one goes.
 */
class FlowMails
{
    public function welcome(FlowRecipient $to): MailContent
    {
        $access = $to->access;
        $name = $to->firstName();

        $mail = MailContent::make(
            $name !== null ? __('Hi :name, I\'m Howy. I remember what you know together.', ['name' => $name]) : __('Hi, I\'m Howy. I remember what you know together.'),
            __('So you don\'t have to keep explaining things, and nothing gets lost.'),
            __('Welcome'),
            $to->account,
        )
            ->heading(__('Hi, I\'m Howy.'), __('I remember what you know together.'))
            ->paragraph($this->greeting($to))
            ->paragraph(__('From today I work alongside you at :account. I keep it simple: one email address, in the inbox you already use every day.', ['account' => $to->account]))
            ->rules($access, $to->address)
            ->paragraph(__('My job: keep the knowledge you share every day, put it in order and find it again. Agreements, decisions, ways of working, the answer to "how do we do that here?". So it doesn\'t only live in someone\'s head or inbox.'))
            ->subheading(__('What\'s in it for you?'))
            ->list($this->benefits($access))
            ->callout('', __('What I\'m not'), items: [
                __('Not a replacement for you. You stay the expert; I\'m the backup of what you know.'),
                __('Not something that reads along. I only see what you send me yourself.'),
                __('Not another app. I simply work in your email.'),
            ])
            ->status($access, $to->account)
            ->paragraph($this->both($access)
                ? __('In my next email I\'ll show how simple it is. Spoiler: there are only two things to remember.')
                : __('In my next email I\'ll show how simple it is. Spoiler: there is only one thing to remember.'));

        if ($access->answers()) {
            $mail->button(__('Ask me your first question'), $this->askLink($to));
        }

        return $this->close($mail, $to, __('Talk soon,'));
    }

    public function howItWorks(FlowRecipient $to): MailContent
    {
        $access = $to->access;

        $mail = MailContent::make(
            match ($access) {
                SenderAccess::Learn => __('Copy me in to save what you know. That\'s all there is to it.'),
                SenderAccess::Answer => __('Email me your question. That\'s all there is to it.'),
                default => __('Cc to save, To to ask. That\'s all there is to it.'),
            },
            $this->both($access) ? __('Two fields you already know. Nothing new to learn.') : __('A field you already know. Nothing new to learn.'),
            __('How it works'),
            $to->account,
        )
            ->heading(__('This is how it works.'), __('Really simple.'))
            ->paragraph($this->greeting($to))
            ->paragraph($this->both($access)
                ? __('Your work already happens in email, so that\'s where I work too. There are only two things to remember:')
                : __('Your work already happens in email, so that\'s where I work too. There is only one thing to remember:'))
            ->rules($access, $to->address);

        if ($access->learns()) {
            $mail->subheading(__('Copy Howy in'), __('Saving knowledge'))
                ->paragraph(__('Sending an email with information that matters for work at :account? Put me in Cc. I stay quiet, so nobody on the thread hears from me, but I take out the knowledge, put it in the right place and keep it up to date. The more often you do it, the more complete the company\'s memory gets.', ['account' => $to->account]))
                ->mockup([
                    ['label' => __('To'), 'value' => 'sanne@supplier.example'],
                    ['label' => __('Cc'), 'value' => $to->address, 'marked' => true],
                    ['label' => __('Subject'), 'value' => __('Delivery agreements 2027')],
                ], __('Hi Sanne, as discussed: delivery within 5 working days, payment within 30 days…'))
                ->list([
                    __('Important processes'),
                    __('Decisions'),
                    __('Ways of working'),
                    __('Agreements with customers and suppliers'),
                    __('"This is how we do it here"'),
                ], __('What do you copy me in on?'))
                ->callout(__('**Better not in Cc:** private matters, personal conversations, passwords and confidential HR matters.'));
        }

        if ($access->answers()) {
            $mail->subheading(__('Put Howy in the To field'), $access->learns() ? __('Asking and sharing') : __('Finding knowledge'))
                ->paragraph(__('Want to know something? Just email me, with me in the To field. Ask it the way you\'d ask a colleague. I reply when I know something relevant, and show you where it comes from.'));

            if ($access->learns()) {
                $mail->paragraph(__('Sending me information directly works too: I learn from it, and I let you know when it contradicts what I already know.'));
            }

            $mail
                ->mockup([
                    ['label' => __('To'), 'value' => $to->address, 'marked' => true],
                    ['label' => __('Subject'), 'value' => __('Payment terms 2027')],
                ], __('Which payment term did we agree with our supplier for 2027?'), __('**Howy:** 30 days, with delivery within 5 working days. Agreed in the email "Delivery agreements 2027" of 14 October.'))
                ->list([
                    __('How does the process for a new customer work?'),
                    __('What did we decide about the new planning?'),
                    __('Who knows most about our supplier contracts?'),
                    __('I\'m new in the team. What should I know this week?'),
                ], __('Questions you could ask'));
        }

        $mail->callout(__('I only see the emails you put me in Cc or To on yourself. You decide what you share.'), __('You stay in control'), 'dashed')
            ->paragraph(__('**Tip:** add :address to your contacts. Then you type "how" and I\'m already there.', ['address' => $to->address]))
            ->status($access, $to->account);

        if ($access->answers()) {
            $mail->button(__('Ask me your first question'), $this->askLink($to));
        }

        return $this->close($mail, $to, __('Talk soon,'));
    }

    /**
     * @param  list<string>  $statements  what the mail added, as Howy wrote it down
     */
    public function firstSave(FlowRecipient $to, string $subject, array $statements, bool $needsReview, string $reviewUrl): MailContent
    {
        $shown = array_slice($statements, 0, 3);
        $more = count($statements) - count($shown);
        $name = $to->firstName();

        if ($more > 0) {
            $shown[] = trans_choice('{1} …and :count more point.|[2,*] …and :count more points.', $more);
        }

        $mail = MailContent::make(
            __('Saved! Here\'s what I learned from your email'),
            __('Your first contribution to what :account knows. Thank you.', ['account' => $to->account]),
            __('Your first contribution'),
            $to->account,
        )
            ->heading(__('Saved.'), $name !== null ? __('Thank you, :name!', ['name' => $name]) : __('Thank you!'))
            ->paragraph(__('You copied me in for the first time. This is what I took from your email:'))
            ->callout('**"'.($subject !== '' ? $subject : __('(no subject)')).'"**', __('From your email'), items: $shown);

        if ($needsReview) {
            $mail->paragraph(__('An admin at :account takes a quick look before it goes into the knowledge base.', ['account' => $to->account]), muted: true);
        }

        $mail->paragraph(__('**Something not right, or not meant to be shared?** Have a look via the button below. You stay in charge.'))
            ->button(__('Review or adjust'), $reviewUrl)
            ->paragraph(__('After this I won\'t confirm every Cc. Just keep copying me in.'));

        return $this->close($mail, $to, __('Talk soon,'));
    }

    public function nudge(FlowRecipient $to): MailContent
    {
        $mail = MailContent::make(
            __('What do you know that nobody else does?'),
            __('No rush. But your next email might be just what a colleague is looking for.'),
            __('A quick question'),
            $to->account,
        )
            ->heading(__('What do you know'), __('that nobody else does?'))
            ->paragraph($this->greeting($to))
            ->paragraph(__('I haven\'t heard from you since my welcome email. No problem: it\'s busy, and new habits take time. But I\'m curious about what you know, because nobody knows it quite like you.'))
            ->subheading(__('Three easy ways to start'))
            ->list([
                __('**That agreement from yesterday.** Confirming something with a customer or supplier? Copy me in.'),
                __('**The explanation you\'ve given three times.** Write it down once, send it to a colleague and copy me in. Done.'),
                __('**A decision from the meeting.** Sending round the action points? I\'ll remember what was decided.'),
            ])
            ->callout(__('**Not sure?** When in doubt, just do it. I only keep what\'s useful.'), tone: 'dashed')
            ->status($to->access, $to->account)
            ->paragraph(__('One Cc is enough to start.'));

        return $this->close($mail, $to, __('Regards,'));
    }

    public function newLink(FlowRecipient $to, string $url, int $hours): MailContent
    {
        $mail = MailContent::make(
            __('Your new link'),
            trans_choice('{1} The link works for :count hour.|[2,*] The link works for :count hours.', $hours),
            __('New link'),
            $to->account,
        )
            ->heading(__('Here\'s your new link.'))
            ->paragraph($this->greeting($to))
            ->paragraph(trans_choice('{1} You asked for a new link. It works for :count hour; after that you can ask for another one.|[2,*] You asked for a new link. It works for :count hours; after that you can ask for another one.', $hours))
            ->button(__('Open the page'), $url)
            ->paragraph(__('Didn\'t ask for this? Then you can ignore this email.'), muted: true)
            ->signoff(__('Regards,'), $this->signature($to))
            ->footer(__('You get this email because a new link was asked for on a Howy page.'));

        return $mail;
    }

    /**
     * What Howy does for this person, as far as their access goes.
     *
     * @return list<string>
     */
    private function benefits(SenderAccess $access): array
    {
        $benefits = [];

        if ($access->learns()) {
            $benefits[] = __('**The same question less often.** You explain something once; after that colleagues can find it with me.');
        }

        if ($access->answers()) {
            $benefits[] = __('**Answers in no time.** No digging through old threads, no interrupting a colleague who is busy.');
        }

        if ($access->learns()) {
            $benefits[] = __('**Really off on holiday.** What you know stays within reach while you\'re away.');
        }

        if ($access->answers()) {
            $benefits[] = __('**Up to speed sooner.** New in a team or a role? What you need is already there.');
        }

        return $benefits;
    }

    private function close(MailContent $mail, FlowRecipient $to, string $greeting): MailContent
    {
        return $mail->signoff($greeting, $this->signature($to))
            ->footer(__('You get this email because :account works with Howy.', ['account' => $to->account]), $to->unsubscribeUrl);
    }

    private function greeting(FlowRecipient $to): string
    {
        $name = $to->firstName();

        return $name !== null ? __('Hi :name,', ['name' => $name]) : __('Hi,');
    }

    private function signature(FlowRecipient $to): string
    {
        return __('The knowledge base of :account · :address', ['account' => $to->account, 'address' => $to->address]);
    }

    private function askLink(FlowRecipient $to): string
    {
        return 'mailto:'.$to->address.'?subject='.rawurlencode(__('My first question'));
    }

    private function both(SenderAccess $access): bool
    {
        return $access->learns() && $access->answers();
    }
}
