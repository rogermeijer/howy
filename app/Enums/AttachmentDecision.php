<?php

namespace App\Enums;

/**
 * What became of a mail attachment. New and known attachments point at the
 * document version they are; the others say why they were left out.
 */
enum AttachmentDecision: string
{
    /** Taken in as a new document (or version), waiting for approval. */
    case New = 'new';

    /** Already in the knowledge base: the mail is added as one more source. */
    case Known = 'known';

    /** Judged not to be knowledge: an invoice, a CV, a logo. */
    case Ignored = 'ignored';

    case Unsupported = 'unsupported';
    case TooLarge = 'too_large';

    /** An image too small to hold anything: a logo, an icon. */
    case Decorative = 'decorative';

    /** A mail attached to the mail, read as forwarded history instead. */
    case Forwarded = 'forwarded';

    case Failed = 'failed';
}
