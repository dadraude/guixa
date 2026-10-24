<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;

/** Internal command for Catalog, run inside its transaction when a producer is deactivated (R3a, D13); it takes no actor. */
final class StopParticipatingWhereNotSent
{
    public function __construct(private readonly CycleRepository $cycles) {}

    public function __invoke(string $producerId): void
    {
        foreach ($this->cycles->list() as $listed) {
            if ($listed->isCancelled() || ! $listed->hasProducer($producerId)) {
                continue;
            }

            // Locked even when it does not participate now, so a racing ParticipateAgain on the same cycle waits.
            $cycle = $this->cycles->getForUpdate($listed->id);

            if ($cycle === null || ! $this->takesPart($cycle, $producerId)) {
                continue;
            }

            $cycle->stopParticipating($producerId);
            $this->cycles->add($cycle);
        }
    }

    /** Cancelled cycles are left alone; a sent producer order will be too once T18 adds the sent state. */
    private function takesPart(Cycle $cycle, string $producerId): bool
    {
        return ! $cycle->isCancelled() && $cycle->isParticipating($producerId);
    }
}
