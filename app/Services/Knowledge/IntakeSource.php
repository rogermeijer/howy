<?php

namespace App\Services\Knowledge;

use App\Enums\SourceKind;
use App\Models\Email;
use App\Models\User;

/**
 * Who or what brings a file in: someone uploading it, or a mail sharing it,
 * with what that mail said about it.
 */
final readonly class IntakeSource
{
    private function __construct(
        public SourceKind $kind,
        public ?User $user = null,
        public ?Email $email = null,
        public ?string $context = null,
    ) {}

    public static function upload(User $user): self
    {
        return new self(SourceKind::Upload, user: $user);
    }

    public static function mail(Email $email, ?string $context = null): self
    {
        return new self(SourceKind::Mail, email: $email, context: $context);
    }
}
