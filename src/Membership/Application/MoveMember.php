<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

/** Links the same account to another household; it also links an unlinked account (R35). */
final class MoveMember
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly AccountRepository $accounts,
        private readonly DeactivateEmptyHousehold $deactivateEmptyHousehold,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $accountId, string $householdId): void
    {
        $actor->assertManager();

        $target = $this->households->get($householdId) ?? throw HouseholdNotFound::withId($householdId);

        $this->transactions->run(function () use ($accountId, $target): void {
            $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);

            if ($account->householdId === $target->id) {
                return;
            }

            $previousHouseholdId = $account->householdId;
            $account->moveTo($target->id);
            $this->accounts->save($account);

            if (! $target->isActive()) {
                $target->reactivate();
                $this->households->save($target);
            }

            ($this->deactivateEmptyHousehold)($previousHouseholdId);
        });
    }
}
