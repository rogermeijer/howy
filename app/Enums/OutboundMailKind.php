<?php

namespace App\Enums;

/**
 * What a mail Howy sent from a mailbox was: one of its own mails to a
 * colleague (the flow), a reply in a thread, or a link someone asked for.
 */
enum OutboundMailKind: string
{
    case Welcome = 'welcome';

    case HowItWorks = 'how_it_works';

    case FirstSave = 'first_save';

    case Nudge = 'nudge';

    case Answer = 'answer';

    case NotFound = 'not_found';

    case Conflict = 'conflict';

    /** A new link, after one in an earlier mail expired. */
    case Link = 'link';

    /**
     * One of Howy's own mails to a colleague: it goes only to whoever may get
     * them, and can be unsubscribed from.
     */
    public function isFlow(): bool
    {
        return in_array($this, [self::Welcome, self::HowItWorks, self::FirstSave, self::Nudge], true);
    }

    /**
     * A reply in the thread of a mail Howy read.
     */
    public function isReply(): bool
    {
        return in_array($this, [self::Answer, self::NotFound, self::Conflict], true);
    }

    /**
     * The colleague's column that records this flow mail went out.
     */
    public function colleagueColumn(): ?string
    {
        return match ($this) {
            self::Welcome => 'welcomed_at',
            self::HowItWorks => 'explained_at',
            self::FirstSave => 'first_saved_at',
            self::Nudge => 'nudged_at',
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Welcome => __('Welcome'),
            self::HowItWorks => __('How Howy works'),
            self::FirstSave => __('Thanks for the first contribution'),
            self::Nudge => __('Reminder to share'),
            self::Answer => __('Answer'),
            self::NotFound => __('No answer found'),
            self::Conflict => __('Conflict'),
            self::Link => __('New link'),
        };
    }
}
