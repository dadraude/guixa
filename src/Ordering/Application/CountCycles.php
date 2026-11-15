<?php

namespace Guixa\Ordering\Application;

use Guixa\Shared\Application\Actor;

final class CountCycles
{
    public function __construct(private readonly ListCycles $listCycles) {}

    /** Active cycles are neither cancelled nor closed. */
    public function __invoke(Actor $actor): CycleCountsData
    {
        $cycles = ($this->listCycles)($actor);

        return new CycleCountsData(
            count($cycles),
            count(array_filter($cycles, fn (CycleData $cycle) => ! $cycle->cancelled && ! $cycle->closed)),
        );
    }
}
