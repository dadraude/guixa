<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\ValueObject\PendingHouseholdPickup;

final readonly class HouseholdSettlement
{
    /** @var list<PendingPickupData> */
    public array $pendingPickups;

    /** @param list<PendingHouseholdPickup> $pendingPickups */
    public function __construct(
        public string $householdId,
        public string $householdName,
        public int $balanceCents,
        array $pendingPickups,
    ) {
        $this->pendingPickups = array_map(
            fn (PendingHouseholdPickup $pickup) => new PendingPickupData($pickup->cycleId, $pickup->pickupDate),
            $pendingPickups,
        );
    }
}
