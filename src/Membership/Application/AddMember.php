<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\EmailAlreadyInUse;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class AddMember
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly AccountRepository $accounts,
        private readonly SendInvitation $sendInvitation,
        private readonly TransactionRunner $transactions,
    ) {}

    /** @return string the new account's id */
    public function __invoke(Actor $actor, string $householdId, string $name, string $email): string
    {
        $actor->assertManager();

        $household = $this->households->get($householdId) ?? throw HouseholdNotFound::withId($householdId);
        $email = Email::from($email);

        if ($this->accounts->emailIsTaken($email)) {
            throw EmailAlreadyInUse::for($email->value);
        }

        $account = Account::inviteMember($this->accounts->nextId(), $name, $email, $household->id);

        $this->transactions->run(function () use ($household, $account): void {
            $this->accounts->save($account);
            $this->reactivate($household);
            ($this->sendInvitation)($account);
        });

        return $account->id;
    }

    private function reactivate(Household $household): void
    {
        if ($household->isActive()) {
            return;
        }

        $household->reactivate();
        $this->households->save($household);
    }
}
