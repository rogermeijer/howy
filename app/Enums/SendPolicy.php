<?php

namespace App\Enums;

/**
 * Who Howy reads mail from on a mailbox, and so who it may answer. Set per
 * mailbox, because the mailbox's own address is what "the same domain" means.
 */
enum SendPolicy: string
{
    /** Only senders on the mailbox's own domain, minus the blacklist. */
    case Domain = 'domain';

    /** Only senders on the whitelist. */
    case Whitelist = 'whitelist';

    /** Anyone, minus the blacklist. */
    case Always = 'always';

    public function label(): string
    {
        return match ($this) {
            self::Domain => __('Same domain'),
            self::Whitelist => __('Whitelist'),
            self::Always => __('Everyone'),
        };
    }

    public function usesWhitelist(): bool
    {
        return $this === self::Whitelist;
    }

    public function usesBlacklist(): bool
    {
        return $this === self::Domain || $this === self::Always;
    }
}
