<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class CancelCycle
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /** The cycle shows as cancelled and its orders are archived (retained as cancelled), since nothing was charged (R4b, S37). */
    public function __invoke(Actor $actor, string $cycleId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $this->transactions->run(function () use ($cycleId, $now) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->cancel($now);
            $this->cycles->add($cycle);
            $this->orders->archiveOfCycle($cycleId);
        });
    }
}
