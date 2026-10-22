<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class InvitationExpired extends DomainException
{
    public static function link(): self
    {
        return new self('This invitation has expired. Ask a manager to send a new one.');
    }
}
