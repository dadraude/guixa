<?php

namespace App\Http;

use Guixa\Membership\Application\MemberView;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class MemberRow
{
    public function __construct(
        public string $accountId,
        public string $name,
        public string $email,
        public bool $invitationPending,
        public ?string $invitationExpiresAt,
        public bool $invitationExpired,
    ) {}

    public static function from(MemberView $member): self
    {
        return new self(
            $member->accountId,
            $member->name,
            $member->email,
            $member->invitationPending,
            $member->invitationExpiresAt,
            $member->invitationExpired,
        );
    }
}
