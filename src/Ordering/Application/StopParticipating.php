<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

final class StopParticipating
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly TransactionRunner $transactions,
    ) {}

    /** The producer's lines in the cycle show as *will not be delivered* and its order cannot be sent (R6c). */
    public function __invoke(Actor $actor, string $cycleId, string $producerId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $this->transactions->run(function () use ($cycleId, $producerId) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->stopParticipating($producerId);
            $this->cycles->add($cycle);
        });
    }
}
