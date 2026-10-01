<?php

namespace App\Console\Commands;

use App\Facades\Tenancy;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Account;
use App\Models\Email;
use App\Models\EmailInterpretation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Interpret a mail by hand: again after a fix, or one that was never queued
 * (an import, a CC). Runs right away unless --queue is given. A reply that
 * already went out is not sent again.
 */
#[Signature('mail:interpret {account : Account id} {email : Email id} {--queue : Queue it instead of running it now}')]
#[Description('Interpret an email: answer its question or file its information')]
class InterpretEmailCommand extends Command
{
    public function handle(): int
    {
        $account = Account::query()->findOrFail((int) $this->argument('account'));

        return Tenancy::for($account, function (): int {
            $email = Email::query()->find((int) $this->argument('email'));

            if ($email === null) {
                $this->components->error('No such email in this account.');

                return self::FAILURE;
            }

            if ($this->option('queue')) {
                InterpretEmail::dispatch($email->id, force: true);
                $this->components->info('Queued.');

                return self::SUCCESS;
            }

            InterpretEmail::dispatchSync($email->id, force: true);

            $interpretation = EmailInterpretation::query()->where('email_id', $email->id)->sole();

            $this->components->twoColumnDetail('Status', $interpretation->status->value);
            $this->components->twoColumnDetail('Mode', $interpretation->mode->value);
            $this->components->twoColumnDetail('Intent', $interpretation->intent->value ?? '–');
            $this->components->twoColumnDetail('Outcome', $interpretation->outcome->value ?? '–');
            $this->components->twoColumnDetail('Reply', $interpretation->reply_status->value);

            if ($interpretation->summary !== null) {
                $this->line($interpretation->summary);
            }

            if ($interpretation->error !== null) {
                $this->components->warn($interpretation->error);
            }

            return self::SUCCESS;
        });
    }
}
