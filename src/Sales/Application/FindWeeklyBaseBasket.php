<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** A household's weekly base basket in a cycle, or null when it has none. */
final class FindWeeklyBaseBasket
{
    public function __construct(private readonly BasketRepository $baskets) {}

    public function __invoke(Actor $actor, string $cycleId, string $householdId): ?BasketData
    {
        Gate::forUser($actor)->authorize('manage-sales');

        foreach ($this->baskets->weeklyOfCycle($cycleId) as $basket) {
            if ($basket->householdId === $householdId && ! $basket->late) {
                return BasketData::from($basket);
            }
        }

        return null;
    }
}
