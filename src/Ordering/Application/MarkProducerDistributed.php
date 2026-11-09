<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

/** Distributes a validated, balanced reception, which makes it read-only; the last producer settles the cycle (R18, R24, S13, S27, S47). */
final class MarkProducerDistributed
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ReceptionLoader $loader,
        private readonly CycleSettlement $settlement,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    public function __invoke(Actor $actor, string $cycleId, string $producerId): void
    {
        Gate::forUser($actor)->authorize('settle-reception');

        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $this->transactions->run(function () use ($cycleId, $producerId, $now) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $balanced = $this->loader->load($cycle, $producerId)->reception->balancedForDistribution();

            $settledNow = $cycle->markDistributed($producerId, $balanced, $now);
            ($this->settlement)($cycle, $settledNow, $now);
            $this->cycles->add($cycle);
        });
    }
}
