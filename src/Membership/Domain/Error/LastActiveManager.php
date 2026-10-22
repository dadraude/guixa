<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class LastActiveManager extends DomainException
{
    public static function forAccount(string $accountId): self
    {
        return new self("Account {$accountId} is the last active manager; it cannot be deactivated.");
    }
}
