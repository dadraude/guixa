<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\WeeklyBasketStates;
use Guixa\Shared\Domain\Clock;

/** Internal call from Sales, in the validation transaction: closes the cycle once every weekly basket, base or late, is finished (R24). */
final class CloseCycleAfterBasketValidation
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly SalesBaskets $baskets,
        private readonly Clock $clock,
    ) {}

    public function __invoke(string $cycleId): void
    {
        $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
        $cycle->close($this->clock->now(), WeeklyBasketStates::areFinished($this->baskets->snapshot($cycleId)->states()));
        $this->cycles->add($cycle);
    }
}
