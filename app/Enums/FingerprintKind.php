<?php

namespace App\Enums;

/**
 * What a mail fingerprint covers: a whole message, or one paragraph of it.
 */
enum FingerprintKind: string
{
    case Message = 'message';
    case Paragraph = 'paragraph';
}
