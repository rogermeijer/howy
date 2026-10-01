<?php

namespace App\Enums;

/**
 * How an email reached the database.
 */
enum EmailSource: string
{
    /** Delivered after a Gmail push notification. */
    case Push = 'push';

    /** Picked up by the scheduled poll or a manual "sync now". */
    case Poll = 'poll';

    /** Imported by hand from the import dialog, or the 30-day backfill. */
    case Import = 'import';
}
