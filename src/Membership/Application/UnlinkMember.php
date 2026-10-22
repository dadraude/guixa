<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

/** The account stays active, without a household (R35). */
final class UnlinkMember
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly DeactivateEmptyHousehold $deactivateEmptyHousehold,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $accountId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($accountId): void {
            $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);
            $previousHouseholdId = $account->householdId;

            $account->unlink();
            $this->accounts->save($account);

            ($this->deactivateEmptyHousehold)($previousHouseholdId);
        });
    }
}
