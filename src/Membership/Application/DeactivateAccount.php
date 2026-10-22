<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\Error\LastActiveManager;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;

final class DeactivateAccount
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly DeactivateEmptyHousehold $deactivateEmptyHousehold,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    public function __invoke(Actor $actor, string $accountId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($accountId): void {
            // Locked first, so two deactivations cannot each see the other manager still active (R36b).
            $this->accounts->lockManagers();

            $account = $this->accounts->get($accountId) ?? throw AccountNotFound::withId($accountId);

            if ($account->isActiveManager() && ! $this->accounts->hasOtherActiveManager($account->id)) {
                throw LastActiveManager::forAccount($account->id);
            }

            $previousHouseholdId = $account->householdId;

            $account->deactivate($this->clock->now());
            $this->accounts->save($account);

            ($this->deactivateEmptyHousehold)($previousHouseholdId);
        });
    }
}
