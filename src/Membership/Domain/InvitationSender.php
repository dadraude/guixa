<?php

namespace Guixa\Membership\Domain;

interface InvitationSender
{
    public function send(Account $account, string $token, Invitation $invitation): void;
}
