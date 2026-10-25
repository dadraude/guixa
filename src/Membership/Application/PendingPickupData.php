<?php

namespace Guixa\Membership\Application;

final readonly class PendingPickupData
{
    public function __construct(public string $cycleId, public string $pickupDate) {}
}
