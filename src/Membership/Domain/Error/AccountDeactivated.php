<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class AccountDeactivated extends DomainException
{
    public static function for(string $accountId): self
    {
        return new self("Account {$accountId} is deactivated; reactivate it first.");
    }
}
