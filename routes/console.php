<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mailboxes:renew-watches')->daily()->withoutOverlapping();
Schedule::command('mailboxes:poll')->everyFiveMinutes()->withoutOverlapping();
