<?php

namespace Guixa\Membership\Application;

final readonly class MemberView
{
    public function __construct(
        public string $accountId,
        public string $name,
        public string $email,
        public bool $invitationPending,
        public ?string $invitationExpiresAt,
        public bool $invitationExpired,
    ) {}
}
