<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ProducerNotActive;
use Guixa\Ordering\Domain\ProducerCutoffRules;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class AddParticipant
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly ProducerCutoffRules $rules,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {}

    /** Makes a producer participate in the cycle; its cutoff comes from its default as it is now (R4a, R5). */
    public function __invoke(Actor $actor, string $cycleId, string $producerId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $rule = $this->rules->activeRuleOf($producerId) ?? throw ProducerNotActive::withId($producerId);
        $now = $this->clock->now()->setTimezone($this->clock->timezone());

        $this->transactions->run(function () use ($cycleId, $producerId, $rule, $now) {
            $cycle = $this->cycles->getForUpdate($cycleId) ?? throw CycleNotFound::withId($cycleId);
            $cycle->addParticipant($producerId, $rule, $now);
            $this->cycles->add($cycle);
        });
    }
}
