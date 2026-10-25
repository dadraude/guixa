<?php

namespace Guixa\Membership\Domain\ValueObject;

final readonly class PendingHouseholdPickup
{
    public function __construct(public string $cycleId, public string $pickupDate) {}
}
