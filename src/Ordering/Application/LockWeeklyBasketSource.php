<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\ReceptionReopened;

/** Internal call from Sales: serialises weekly basket validation with source changes on the cycle row (R18, R24). */
final class LockWeeklyBasketSource
{
    public function __construct(private readonly CycleRepository $cycles) {}

    /** Call it inside the caller's transaction; the lock lasts until that transaction ends. */
    public function __invoke(string $cycleId): void
    {
        $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);

        if (! $cycle->isSettled()) {
            throw CycleNotSettled::withId($cycleId);
        }

        // A producer reopened after settlement makes the source stale until it is distributed again (R18).
        if (! $cycle->isFullyDistributed()) {
            throw ReceptionReopened::forCycle($cycleId);
        }
    }
}
