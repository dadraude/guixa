<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\HouseholdBalances;
use Guixa\Membership\Domain\HouseholdPickups;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class GetHouseholdDeactivationWarning
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly HouseholdBalances $balances,
        private readonly HouseholdPickups $pickups,
    ) {}

    public function __invoke(Actor $actor, string $householdId): HouseholdSettlement
    {
        Gate::forUser($actor)->authorize('manage');
        $household = $this->households->get($householdId) ?? throw HouseholdNotFound::withId($householdId);

        return new HouseholdSettlement(
            $household->id,
            $household->name,
            $this->balances->balanceCents($householdId),
            $this->pickups->pendingFor($householdId),
        );
    }
}
