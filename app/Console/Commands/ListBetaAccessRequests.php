<?php

namespace App\Console\Commands;

use App\Models\BetaAccessRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The email addresses that asked for a private beta invite, oldest first, with
 * their codes. Inviting them happens in /admin/beta.
 */
#[Signature('beta-access:list')]
#[Description('List private beta access requests')]
class ListBetaAccessRequests extends Command
{
    public function handle(): int
    {
        $this->table(
            ['Email', 'Language', 'Requested', 'Code', 'Status'],
            BetaAccessRequest::query()->oldest('id')->get()
                ->map(fn (BetaAccessRequest $request): array => [
                    $request->email,
                    $request->locale ?? '',
                    $request->created_at?->toDateTimeString() ?? '',
                    $request->code,
                    $request->status(),
                ])->all(),
        );

        return self::SUCCESS;
    }
}
