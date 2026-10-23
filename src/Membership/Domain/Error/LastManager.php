<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class LastManager extends DomainException
{
    public static function forAccount(string $accountId): self
    {
        return new self("Account {$accountId} is the last active manager; grant the role to someone else first.");
    }
}
