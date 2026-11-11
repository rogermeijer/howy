<?php

namespace App\Http\Controllers\Concerns;

use App\Facades\Tenancy;
use App\Models\Colleague;
use App\Services\Adoption\Welcomes;
use Inertia\Inertia;

/**
 * Whether the account is live, and who the welcome would go to on going live,
 * for the settings page and the end of setup.
 */
trait PresentsGoLive
{
    /**
     * @return array<string, mixed>
     */
    protected function goLiveProps(): array
    {
        return [
            'live' => ['since' => Tenancy::account()->live_at?->toIso8601String()],
            // Loaded once the admin opens the dialog to go live.
            'welcomeList' => Inertia::optional(fn (): array => app(Welcomes::class)->waiting()
                ->map(fn (Colleague $colleague): array => [
                    'id' => $colleague->id,
                    'name' => $colleague->name,
                    'email' => $colleague->email,
                    'mailbox' => $colleague->homeMailbox?->email_address,
                ])
                ->all()),
        ];
    }
}
