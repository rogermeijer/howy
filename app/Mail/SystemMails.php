<?php

namespace App\Mail;

use App\Mail\Content\MailContent;

/**
 * The mails about someone's own account or access, sent by the app rather
 * than from a mailbox: the beta invite, a mailbox that needs connecting again,
 * a password reset and confirming an email address. In Howy's voice, in the
 * same frame as everything else Howy sends.
 */
class SystemMails
{
    public function betaInvite(string $code, string $url): MailContent
    {
        return MailContent::make(__('Your invite to Howy'), __('There is room for your team now. Here is your code.'), __('Invite'))
            ->heading(__('You\'re in.'))
            ->paragraph(__('Thanks for your patience. There is room for your team in the Howy private beta now.'))
            ->paragraph(__('Your invite code is:'))
            ->code($code)
            ->button(__('Create your account'), $url)
            ->paragraph(__('The code works once: use it to create your team\'s account.'), muted: true)
            ->signoff(__('See you in your threads,'))
            ->footer(__('You get this email because you asked for access to the Howy beta.'));
    }

    public function mailboxNeedsReconnecting(string $address, string $account, string $url): MailContent
    {
        return MailContent::make(
            __(':address is no longer connected to Howy', ['address' => $address]),
            __('New email there is not reaching me until it is connected again.'),
            __('Action needed'),
            $account,
        )
            ->heading(__(':address is no longer connected', ['address' => $address]))
            ->paragraph(__('Google no longer gives me access to :address, so new emails there aren\'t reaching :account.', ['address' => $address, 'account' => $account]))
            ->paragraph(__('This happens when access was removed in the Google account, its password was changed, or the mailbox was disconnected from Howy elsewhere.'))
            ->button(__('Connect it again'), $url)
            ->paragraph(__('The emails I already have stay where they are. Once the mailbox is connected again, I catch up on recent mail; older emails can be imported.'), muted: true)
            ->signoff(__('Regards,'))
            ->footer(__('You get this email because you are an admin of :account in Howy.', ['account' => $account]));
    }

    public function resetPassword(?string $name, string $email, string $url, int $minutes): MailContent
    {
        return MailContent::make(__('Reset your Howy password'), __('Choose a new password with the link in this email.'), __('Your account'))
            ->heading(__('Reset your password.'))
            ->paragraph($this->greeting($name))
            ->paragraph(__('Someone (hopefully you) asked to reset the password of your Howy account.'))
            ->button(__('Choose a new password'), $url)
            ->paragraph(trans_choice('{1} The link works for :count minute. Didn\'t ask for this? Then you don\'t need to do anything; your password stays as it is.|[2,*] The link works for :count minutes. Didn\'t ask for this? Then you don\'t need to do anything; your password stays as it is.', $minutes), muted: true)
            ->signoff(__('Regards,'))
            ->footer(__('You get this email because a password reset was asked for :email.', ['email' => $email]));
    }

    public function verifyEmail(?string $name, string $email, string $url): MailContent
    {
        return MailContent::make(__('Confirm your email address'), __('One click and you can get started with Howy.'), __('Your account'))
            ->heading(__('One more step.'))
            ->paragraph($this->greeting($name))
            ->paragraph(__('Confirm that this is your email address, and you can get started with Howy.'))
            ->button(__('Confirm my email address'), $url)
            ->paragraph(__('Didn\'t create a Howy account? Then you can ignore this email.'), muted: true)
            ->signoff(__('See you soon,'))
            ->footer(__('You get this email because a Howy account was created with :email.', ['email' => $email]));
    }

    private function greeting(?string $name): string
    {
        $first = trim((string) strtok(trim((string) $name), ' '));

        return $first !== '' ? __('Hi :name,', ['name' => $first]) : __('Hi,');
    }
}
