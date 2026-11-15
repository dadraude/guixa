<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

/** Takes a distributed producer back to its reception, e.g. when goods arrive late; allowed until the cycle closes (R18, S29). */
final class ReopenProducerOrder
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly TransactionRunner $transactions,
    ) {}

    public function __invoke(Actor $actor, string $cycleId, string $producerId): void
    {
        Gate::forUser($actor)->authorize('settle-reception');

        $this->transactions->run(function () use ($cycleId, $producerId) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->reopen($producerId);
            $this->cycles->add($cycle);
        });
    }
}
