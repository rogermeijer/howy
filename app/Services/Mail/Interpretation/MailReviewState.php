<?php

namespace App\Services\Mail\Interpretation;

use App\Enums\ProcessingStatus;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\EmailInterpretation;

/**
 * Whether a mail waits for an administrator: a statement it makes, or a
 * document it brought that is processed and not approved yet. The inbox
 * counts and opens what waits.
 */
class MailReviewState
{
    public function refresh(Email $email): void
    {
        $interpretation = EmailInterpretation::query()->where('email_id', $email->id)->first();

        if ($interpretation === null) {
            return;
        }

        $statements = in_array('pending', array_column($interpretation->statements ?? [], 'review'), true);

        $documents = DocumentSource::query()
            ->where('email_id', $email->id)
            ->whereHas('version', fn ($query) => $query->where('status', ProcessingStatus::Review)->whereNull('approved_at'))
            ->exists();

        $interpretation->update(['needs_review' => $statements || $documents]);
    }

    /**
     * Every mail that brought this version.
     */
    public function refreshFor(DocumentVersion $version): void
    {
        $emails = Email::query()->whereIn('id', DocumentSource::query()->select('email_id')->where('document_version_id', $version->id)->whereNotNull('email_id'))->get();

        foreach ($emails as $email) {
            $this->refresh($email);
        }
    }
}
