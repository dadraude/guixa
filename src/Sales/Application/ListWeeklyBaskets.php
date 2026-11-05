<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** The weekly baskets of a settled cycle, by household name, each base before its late baskets. */
final class ListWeeklyBaskets
{
    public function __construct(private readonly WeeklyBasketsOfCycle $weeklyBaskets) {}

    /** @return list<BasketData> */
    public function __invoke(Actor $actor, string $cycleId): array
    {
        Gate::forUser($actor)->authorize('manage-sales');

        $baskets = ($this->weeklyBaskets)($cycleId);
        usort($baskets, fn (Basket $a, Basket $b) => [mb_strtolower($a->householdName), $a->householdId, $a->late, $a->id]
            <=> [mb_strtolower($b->householdName), $b->householdId, $b->late, $b->id]);

        return array_map(BasketData::from(...), $baskets);
    }
}
