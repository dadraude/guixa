<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

final class GrantFinanceRole
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly HouseholdRepository $households,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $accountId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $this->transactions->run(function () use ($accountId): void {
            $this->households->lockMembershipChanges();
            $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);
            $account->grantFinance();
            $this->accounts->save($account);
        });
    }
}
