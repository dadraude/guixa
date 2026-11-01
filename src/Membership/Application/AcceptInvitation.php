<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountPasswords;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\InvitationExpired;
use Guixa\Membership\Domain\Error\InvitationNotValid;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Membership\Domain\Invitation;
use Guixa\Membership\Domain\InvitationRepository;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;

/** Open to anyone holding the link: the invitation token is the credential. */
final class AcceptInvitation
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly AccountRepository $accounts,
        private readonly HouseholdRepository $households,
        private readonly AccountPasswords $passwords,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    public function __invoke(string $token, string $password): void
    {
        $this->transactions->run(function () use ($token, $password): void {
            $this->households->lockMembershipChanges();
            $invitation = $this->invitations->findByTokenHash(Invitation::hash($token)) ?? throw InvitationNotValid::link();
            $now = $this->clock->now();

            if ($invitation->isExpiredAt($now)) {
                throw InvitationExpired::link();
            }

            $account = $this->accounts->get($invitation->accountId) ?? throw InvitationNotValid::link();
            $account->acceptInvitation($now);
            $this->accounts->save($account);
            $this->passwords->change($account->id, $password);
            $this->invitations->deleteForAccount($account->id);
        });
    }
}
