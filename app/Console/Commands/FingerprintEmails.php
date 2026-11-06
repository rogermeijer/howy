<?php

namespace App\Console\Commands;

use App\Enums\FingerprintUse;
use App\Enums\InterpretationStatus;
use App\Enums\ReplyStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Email;
use App\Services\Mail\MailFingerprints;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Remembers what was read before fingerprints existed, so a forward or reply
 * of a mail interpreted earlier is recognised too. Safe to rerun: what is
 * known stays where it was first read.
 */
#[Signature('emails:fingerprint')]
#[Description('Fingerprint every interpreted email and every reply Howy sent')]
class FingerprintEmails extends Command
{
    public function handle(MailFingerprints $fingerprints): int
    {
        $count = 0;

        Account::query()->each(function (Account $account) use ($fingerprints, &$count): void {
            Tenancy::for($account, function () use ($fingerprints, &$count): void {
                Email::query()->with('interpretation')->oldest('received_at')->oldest('id')->chunkById(200, function ($emails) use ($fingerprints, &$count): void {
                    foreach ($emails as $email) {
                        $text = (string) ($email->content_text ?? $email->body_text ?? '');
                        $email->forceFill(['content_hash' => $email->content_text === null ? null : MailFingerprints::hash($email->content_text)])->save();

                        $interpretation = $email->interpretation;

                        if ($interpretation?->status === InterpretationStatus::Done) {
                            $fingerprints->record($email, $text, FingerprintUse::Read);
                            $count++;
                        }

                        if ($interpretation?->reply_status === ReplyStatus::Sent && $interpretation->reply_text !== null) {
                            $fingerprints->record($email, $interpretation->reply_text, FingerprintUse::Reply);
                        }
                    }
                });
            });
        });

        $this->components->info("Fingerprinted {$count} emails.");

        return self::SUCCESS;
    }
}
