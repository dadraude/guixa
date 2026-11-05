<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** Finance and Manager read every household's pending collections, optionally those of one weekly cycle. */
final class ListPendingCollections
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /** @return list<BasketData> ordered by validation instant, then id */
    public function __invoke(Actor $actor, ?string $cycleId = null): array
    {
        Gate::forUser($actor)->authorize('manage-sales');

        $pending = $this->baskets->pendingCollections();
        if ($cycleId !== null) {
            $pending = array_values(array_filter($pending, fn ($basket) => $basket->isWeekly() && $basket->referenceCycleId === $cycleId));
        }

        return array_map(BasketData::from(...), $pending);
    }
}
