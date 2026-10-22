<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class AccountNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("There is no account {$id}.");
    }
}
