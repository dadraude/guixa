<?php

namespace Guixa\Sales\Application;

use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** A household's weekly base basket in a cycle, or null when it has none. */
final class FindWeeklyBaseBasket
{
    public function __construct(private readonly WeeklyBasketsOfCycle $weeklyBaskets) {}

    public function __invoke(Actor $actor, string $cycleId, string $householdId): ?BasketData
    {
        Gate::forUser($actor)->authorize('manage-sales');

        foreach (($this->weeklyBaskets)($cycleId) as $basket) {
            if ($basket->householdId === $householdId && ! $basket->late) {
                return BasketData::from($basket);
            }
        }

        return null;
    }
}
