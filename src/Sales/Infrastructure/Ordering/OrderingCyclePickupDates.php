<?php

namespace Guixa\Sales\Infrastructure\Ordering;

use Guixa\Ordering\Application\ReadCyclePickupDates;
use Guixa\Sales\Domain\CyclePickupDates;

final class OrderingCyclePickupDates implements CyclePickupDates
{
    public function __construct(private readonly ReadCyclePickupDates $readPickupDates) {}

    public function forCycles(array $cycleIds): array
    {
        return ($this->readPickupDates)($cycleIds);
    }
}
