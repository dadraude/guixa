<?php

namespace Guixa\Membership\Domain\Error;

use DomainException;

final class InvitationNotValid extends DomainException
{
    public static function link(): self
    {
        return new self('This invitation link is not valid. Ask a manager to send a new one.');
    }
}
