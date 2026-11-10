<?php

namespace Tests\Concerns;

use App\Enums\ProcessingStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Knowledge\Review\DocumentReview;

/**
 * Every new version waits for an administrator. Tests about search and
 * enrichment approve what was processed, as that administrator would.
 */
trait ApprovesDocuments
{
    protected function approvePending(Account $account, User $reviewer): void
    {
        Tenancy::for($account, function () use ($reviewer): void {
            $versions = DocumentVersion::query()
                ->where('status', ProcessingStatus::Review)
                ->whereNull('approved_at')
                ->orderBy('version_number')
                ->get();

            foreach ($versions as $version) {
                app(DocumentReview::class)->approve($version, $reviewer);
            }
        });
    }
}
