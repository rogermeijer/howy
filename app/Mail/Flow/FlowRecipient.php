<?php

namespace App\Mail\Flow;

use App\Enums\SenderAccess;

/**
 * Who a flow mail is for, as far as the mail needs to know: their first
 * name when known, what Howy does with their mail, the account and the
 * address of the mailbox they write to, and their way out.
 */
final readonly class FlowRecipient
{
    public function __construct(
        public ?string $name,
        public SenderAccess $access,
        public string $account,
        public string $address,
        public ?string $unsubscribeUrl = null,
    ) {}

    /**
     * The first word of the name, for "Hi Sanne,".
     */
    public function firstName(): ?string
    {
        $first = trim((string) strtok(trim((string) $this->name), ' '));

        return $first !== '' ? $first : null;
    }
}
