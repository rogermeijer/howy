<?php

namespace App\Services\Knowledge\Review;

use App\Enums\ProcessingStatus;
use App\Models\DocumentVersion;
use App\Models\EmailInterpretation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everything that waits for an administrator before it reaches the knowledge
 * base, in one place: document versions that are processed, uploaded or
 * mailed, and the statements mail would add or change. A mail itself never
 * waits; what it brought does, here.
 */
class ApprovalQueue
{
    /**
     * @return Builder<DocumentVersion>
     */
    public function documents(): Builder
    {
        return DocumentVersion::query()->where('status', ProcessingStatus::Review)->whereNull('approved_at');
    }

    /**
     * Mails with at least one statement waiting.
     *
     * @return Builder<EmailInterpretation>
     */
    public function mails(): Builder
    {
        return EmailInterpretation::query()->where('needs_review', true);
    }

    /**
     * What the top bar counts: a document, or a mail's statements, each one
     * thing to look at.
     */
    public function count(): int
    {
        return $this->documents()->count() + $this->mails()->count();
    }
}
