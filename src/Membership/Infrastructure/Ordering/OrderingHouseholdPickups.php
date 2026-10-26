<?php

namespace Guixa\Membership\Infrastructure\Ordering;

use Guixa\Membership\Domain\HouseholdPickups;
use Guixa\Membership\Domain\ValueObject\PendingHouseholdPickup;
use Guixa\Ordering\Application\PendingPickupData;
use Guixa\Ordering\Application\ReadPendingPickups;

final class OrderingHouseholdPickups implements HouseholdPickups
{
    public function __construct(private readonly ReadPendingPickups $readPendingPickups) {}

    public function pendingFor(string $householdId): array
    {
        return array_map(
            fn (PendingPickupData $pickup) => new PendingHouseholdPickup($pickup->cycleId, $pickup->pickupDate),
            ($this->readPendingPickups)($householdId),
        );
    }
}
