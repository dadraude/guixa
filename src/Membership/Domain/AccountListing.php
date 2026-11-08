<?php

namespace Guixa\Membership\Domain;

use DateTimeImmutable;

/** What a list of accounts shows about one account: identity and how far its invitation got (R10). */
final readonly class AccountListing
{
    public function __construct(
        public string $accountId,
        public string $name,
        public string $email,
        public ?DateTimeImmutable $activatedAt,
        public ?DateTimeImmutable $deactivatedAt,
        public ?DateTimeImmutable $invitationSentAt,
    ) {}

    public function status(DateTimeImmutable $now): AccountStatus
    {
        if ($this->deactivatedAt !== null) {
            return AccountStatus::Deactivated;
        }

        if ($this->activatedAt !== null) {
            return AccountStatus::Active;
        }

        if ($this->invitationSentAt === null) {
            return AccountStatus::InvitationPending;
        }

        return Invitation::restore($this->accountId, '', $this->invitationSentAt)->isExpiredAt($now)
            ? AccountStatus::InvitationExpired
            : AccountStatus::InvitationPending;
    }
}
