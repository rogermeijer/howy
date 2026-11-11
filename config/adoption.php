<?php

/*
 * The mails that help colleagues start with Howy (docs/adoption-mails.md).
 * Every interval is configurable, so the rhythm can change without a deploy
 * of code: only the environment.
 */
return [

    // Working days (Monday to Friday, in the account's timezone) between the
    // welcome mail and "how it works".
    'how_it_works_after_workdays' => (int) env('ADOPTION_HOW_IT_WORKS_AFTER_WORKDAYS', 2),

    // Working days between the welcome mail and the nudge for someone who has
    // not mailed Howy since.
    'nudge_after_workdays' => (int) env('ADOPTION_NUDGE_AFTER_WORKDAYS', 5),

    // Scheduled flow mails go out between these hours, in the account's
    // timezone. A welcome sent by hand goes at once.
    'send_from_hour' => (int) env('ADOPTION_SEND_FROM_HOUR', 9),
    'send_until_hour' => (int) env('ADOPTION_SEND_UNTIL_HOUR', 17),

    // How long a link in a mail works without logging in (rating an answer,
    // looking over what was saved). After that the page offers a new link.
    'mail_link_hours' => (int) env('MAIL_LINK_HOURS', 48),

];
