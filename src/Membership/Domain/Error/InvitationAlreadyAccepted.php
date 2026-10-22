<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class InvitationAlreadyAccepted extends DomainException
{
    public static function for(string $accountId): self
    {
        return new self("Account {$accountId} already accepted its invitation.");
    }
}
