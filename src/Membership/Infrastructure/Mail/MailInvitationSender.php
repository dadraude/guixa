<?php

namespace Guixa\Membership\Infrastructure\Mail;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\Invitation;
use Guixa\Membership\Domain\InvitationSender;
use Illuminate\Support\Facades\Mail;

final class MailInvitationSender implements InvitationSender
{
    public function send(Account $account, string $token, Invitation $invitation): void
    {
        Mail::to($account->email->value)->send(new InvitationMail(
            $account->name,
            route('invitation.show', ['token' => $token]),
            $invitation->expiresAt(),
        ));
    }
}
