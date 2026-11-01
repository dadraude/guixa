<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\Error\EmailAlreadyInUse;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

final class ChangeInvitationEmail
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly HouseholdRepository $households,
        private readonly SendInvitation $sendInvitation,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $accountId, string $email): void
    {
        Gate::forUser($actor)->authorize('manage');
        $email = Email::from($email);

        $this->transactions->run(function () use ($accountId, $email): void {
            $this->households->lockMembershipChanges();
            $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);
            $account->assertInvitationPending();

            if ($email->value !== $account->email->value && $this->accounts->emailIsTaken($email)) {
                throw EmailAlreadyInUse::for($email->value);
            }

            $account->changeInvitationEmail($email);
            $this->accounts->save($account);
            ($this->sendInvitation)($account);
        });
    }
}
