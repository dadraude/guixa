<?php

namespace Guixa\Ordering\Application;

use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** How many weekly baskets of each cycle still wait for collection, so a closed cycle keeps showing them (R23a, R24b). */
final class CountPendingCollectionsByCycle
{
    public function __construct(private readonly SalesBaskets $baskets) {}

    /** @return array<string, int> by cycle id; cycles with none are absent */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('manage');

        return $this->baskets->pendingCollectionCounts();
    }
}
