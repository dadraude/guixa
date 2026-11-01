<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class RemoveFinanceRole
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly HouseholdRepository $households,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $accountId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($accountId): void {
            $this->households->lockMembershipChanges();
            $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);
            $account->removeFinance();
            $this->accounts->save($account);
        });
    }
}
