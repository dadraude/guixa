<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ProducerNotActive;
use Guixa\Ordering\Domain\ProducerCutoffRules;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class ParticipateAgain
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ProducerCutoffRules $rules,
        private readonly TransactionRunner $transactions,
    ) {}

    /** A deactivated producer cannot be made to participate (R3a); the cutoff it had stays (R6c). */
    public function __invoke(Actor $actor, string $cycleId, string $producerId): void
    {
        $actor->assertManager();

        $this->transactions->run(function () use ($cycleId, $producerId) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);

            // Checked under the cycle lock, so a deactivation running at the same time is seen or waits.
            if ($this->rules->activeRuleOf($producerId) === null) {
                throw ProducerNotActive::withId($producerId);
            }

            $cycle->participateAgain($producerId);
            $this->cycles->add($cycle);
        });
    }
}
