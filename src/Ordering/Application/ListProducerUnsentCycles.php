<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** The cycles where a producer's availability can still change, without any of their totals (D9, R14). */
final class ListProducerUnsentCycles
{
    public function __construct(private readonly CycleRepository $cycles) {}

    /** @return list<ProducerUnsentCycleData> by pickup date, earliest first */
    public function __invoke(Actor $actor, string $producerId): array
    {
        Gate::forUser($actor)->authorize('set-availability', $producerId);

        $unsent = array_filter(
            $this->cycles->list(),
            fn (Cycle $cycle) => ! $cycle->isCancelled() && $cycle->isParticipating($producerId) && ! $cycle->isSent($producerId),
        );

        return array_values(array_map(
            fn (Cycle $cycle) => new ProducerUnsentCycleData($cycle->id, $cycle->pickupDate()->format('Y-m-d')),
            $unsent,
        ));
    }
}
