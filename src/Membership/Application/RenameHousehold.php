<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

final class RenameHousehold
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $householdId, string $name): void
    {
        Gate::forUser($actor)->authorize('manage');

        $this->transactions->run(function () use ($householdId, $name): void {
            $this->households->lockMembershipChanges();
            $household = $this->households->get($householdId) ?? throw HouseholdNotFound::withId($householdId);
            $household->rename($name);
            $this->households->save($household);
        });
    }
}
