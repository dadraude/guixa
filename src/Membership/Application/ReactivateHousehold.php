<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class ReactivateHousehold
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $householdId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($householdId): void {
            $this->households->lockMembershipChanges();
            $household = $this->households->get($householdId) ?? throw HouseholdNotFound::withId($householdId);
            $household->reactivate();
            $this->households->save($household);
        });
    }
}
