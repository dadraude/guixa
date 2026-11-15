<?php

namespace Guixa\Stock\Infrastructure\Ordering;

use Guixa\Ordering\Application\ReadCyclePickupDates;
use Guixa\Stock\Domain\CyclePickupDates;

/** Answers Stock's question about the cycle of a movement through Ordering's use case. */
final class OrderingCyclePickupDates implements CyclePickupDates
{
    public function __construct(private readonly ReadCyclePickupDates $readPickupDates) {}

    public function forCycles(array $cycleIds): array
    {
        return ($this->readPickupDates)($cycleIds);
    }
}
