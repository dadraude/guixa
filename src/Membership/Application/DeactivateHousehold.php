<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;

final class DeactivateHousehold
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly AccountRepository $accounts,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    public function __invoke(Actor $actor, string $householdId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($householdId): void {
            $this->households->lockMembershipChanges();
            $household = $this->households->get($householdId) ?? throw HouseholdNotFound::withId($householdId);

            foreach ($this->accounts->membersOf($householdId) as $account) {
                $account->unlink();
                $this->accounts->saveHouseholdLink($account);
            }

            $household->deactivate($this->clock->now());
            $this->households->save($household);
        });
    }
}
