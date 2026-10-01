<?php

namespace App\Enums;

/**
 * Whether cc: may reply from a mailbox, and to whom. Set per mailbox, because
 * the mailbox's own address is what "the same domain" means.
 */
enum SendPolicy: string
{
    /** Never reply. */
    case Off = 'off';

    /** Only to recipients on the mailbox's own domain, minus the blacklist. */
    case Domain = 'domain';

    /** Only to recipients on the whitelist. */
    case Whitelist = 'whitelist';

    /** To anyone, minus the blacklist. */
    case Always = 'always';

    public function label(): string
    {
        return match ($this) {
            self::Off => __('Off'),
            self::Domain => __('Same domain'),
            self::Whitelist => __('Whitelist'),
            self::Always => __('Always'),
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
