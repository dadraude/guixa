<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\Invitation;
use Guixa\Membership\Domain\InvitationRepository;
use Guixa\Membership\Domain\InvitationSender;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;

/** Issues a fresh invitation for the account and emails it once the surrounding transaction commits. */
final class SendInvitation
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly InvitationSender $sender,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    public function __invoke(Account $account): void
    {
        $token = Invitation::newToken();
        $invitation = Invitation::issue($account->id, $token, $this->clock->now());

        $this->invitations->save($invitation);
        $this->transactions->afterCommit(fn () => $this->sender->send($account, $token, $invitation));
    }
}
