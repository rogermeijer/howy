<?php

namespace App\Enums;

/**
 * How text that Howy has seen was handled, so a later copy of it is
 * recognised for what it is.
 */
enum FingerprintUse: string
{
    /** Read as a mail and interpreted. */
    case Read = 'read';

    /** Quoted or forwarded history whose statements were taken from it. */
    case Statements = 'statements';

    /** Quoted or forwarded history filed as a correspondence document. */
    case Document = 'document';

    /** What Howy itself wrote into the thread. */
    case Reply = 'reply';
}
