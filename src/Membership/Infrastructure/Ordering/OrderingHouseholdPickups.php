<?php

namespace Guixa\Membership\Infrastructure\Ordering;

use Guixa\Membership\Domain\HouseholdPickups;
use Guixa\Membership\Domain\ValueObject\PendingHouseholdPickup;
use Guixa\Ordering\Application\ListPendingPickups;
use Guixa\Ordering\Application\PendingPickupData;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;

final class OrderingHouseholdPickups implements HouseholdPickups
{
    public function __construct(private readonly ListPendingPickups $listPendingPickups) {}

    public function pendingFor(string $householdId): array
    {
        return array_map(
            fn (PendingPickupData $pickup) => new PendingHouseholdPickup($pickup->cycleId, $pickup->pickupDate),
            ($this->listPendingPickups)(new Actor('system', [Role::Manager]), $householdId),
        );
    }
}
