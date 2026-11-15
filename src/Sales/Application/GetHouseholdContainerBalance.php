<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\ContainerBalance;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** The containers a household still has to return, across all its baskets and days (R18a). */
final class GetHouseholdContainerBalance
{
    public function __construct(private readonly BasketRepository $baskets) {}

    public function __invoke(Actor $actor, string $householdId): int
    {
        Gate::forUser($actor)->authorize('collectFor', ['household', $householdId]);

        return ContainerBalance::of($this->baskets->settledForHouseholds([$householdId]));
    }
}
