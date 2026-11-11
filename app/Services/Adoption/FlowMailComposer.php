<?php

namespace App\Services\Adoption;

use App\Enums\OutboundMailKind;
use App\Enums\SenderAccess;
use App\Facades\Tenancy;
use App\Mail\Content\MailContent;
use App\Mail\Content\MailRenderer;
use App\Mail\Content\RenderedMail;
use App\Mail\Flow\FlowMails;
use App\Mail\Flow\FlowRecipient;
use App\Models\Colleague;
use App\Models\Email;
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
     * Whether this is one of Howy's own mails to a colleague it writes. The
     * thanks for a first contribution also needs the mail it thanks for.
     */
    public static function writes(OutboundMailKind $kind): bool
    {
        return $kind->isFlow();
    }

    public function compose(Colleague $colleague, OutboundMailKind $kind, ?Email $about = null): RenderedMail
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
            OutboundMailKind::FirstSave => $this->firstSave($to, $about ?? throw new InvalidArgumentException('A thanks for a contribution needs the mail it came in.')),
            default => throw new InvalidArgumentException("[{$kind->value}] is not a flow mail of its own."),
        }));
    }

    /**
     * What the mail added, as Howy wrote it down: its new statements, none an
     * admin turned down. No button to look it over yet: the page for that,
     * open without logging in, is still to come.
     */
    private function firstSave(FlowRecipient $to, Email $about): MailContent
    {
        $interpretation = $about->interpretation;
        $statements = array_values(array_map(
            fn (array $statement): string => $statement['statement'],
            array_filter(
                $interpretation->statements ?? [],
                fn (array $statement): bool => $statement['verdict'] === 'new' && ($statement['review'] ?? null) !== 'rejected',
            ),
        ));

        return $this->flow->firstSave($to, trim((string) $about->subject), $statements, (bool) $interpretation?->needs_review, null);
    }
}
