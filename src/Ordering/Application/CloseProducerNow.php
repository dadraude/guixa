<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;

final class CloseProducerNow
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /** The producer's cutoff becomes the current moment (R6). */
    public function __invoke(Actor $actor, string $cycleId, string $producerId): void
    {
        $actor->assertManager();

        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $this->transactions->run(function () use ($cycleId, $producerId, $now) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->closeNow($producerId, $now);
            $this->cycles->add($cycle);
        });
    }
}
