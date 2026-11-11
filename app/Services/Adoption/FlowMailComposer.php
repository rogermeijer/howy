<?php

namespace App\Services\Adoption;

use App\Enums\OutboundMailKind;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Mail\Content\MailRenderer;
use App\Mail\Content\RenderedMail;
use App\Mail\Flow\FlowMails;
use App\Mail\Flow\FlowRecipient;
use App\Models\Colleague;
use Illuminate\Support\Traits\Localizable;
use InvalidArgumentException;

/**
 * One of Howy's own mails, written for one colleague: in their language (a
 * member's own, else the account's), saying only what their access on their
 * home mailbox allows.
 */
class FlowMailComposer
{
    use Localizable;

    public function __construct(
        private readonly FlowMails $flow,
        private readonly MailRenderer $renderer,
    ) {}

    /**
     * Whether this is a mail it writes: one of Howy's own mails to a colleague
     * that needs nothing but the colleague.
     */
    public static function writes(OutboundMailKind $kind): bool
    {
        return in_array($kind, [OutboundMailKind::Welcome, OutboundMailKind::HowItWorks, OutboundMailKind::Nudge], true);
    }

    public function compose(Colleague $colleague, OutboundMailKind $kind): RenderedMail
    {
        $home = $colleague->homeMailbox;
        $to = new FlowRecipient(
            $colleague->name,
            $home?->access($colleague->email) ?? SenderAccess::Ignore,
            Tenancy::account()->name,
            (string) $home?->email_address,
        );
        $locale = $colleague->user?->locale->value ?? Tenancy::account()->locale->value;

        return $this->withLocale($locale, fn (): RenderedMail => $this->renderer->render(match ($kind) {
            OutboundMailKind::Welcome => $this->flow->welcome($to),
            OutboundMailKind::HowItWorks => $this->flow->howItWorks($to),
            OutboundMailKind::Nudge => $this->flow->nudge($to),
            default => throw new InvalidArgumentException("[{$kind->value}] is not a flow mail of its own."),
        }));
    }
}
