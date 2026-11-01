<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

final class ClearCutoff
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly TransactionRunner $transactions,
    ) {}

    /** The producer stays open until a manager closes it, or until the pickup date at 00:00 (R6, R6a). */
    public function __invoke(Actor $actor, string $cycleId, string $producerId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $this->transactions->run(function () use ($cycleId, $producerId) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->clearCutoff($producerId);
            $this->cycles->add($cycle);
        });
    }
}
