<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** A member reads the pending collections of their current household only, closed-cycle and cycleless ones included (R24, R35). */
final class ListMemberPendingCollections
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /** @return list<BasketData> ordered by validation instant, then id */
    public function __invoke(Actor $actor): array
    {
        $householdId = $actor->householdId ?? '';
        Gate::forUser($actor)->authorize('memberOf', ['household', $householdId]);

        return array_map(fn ($basket) => BasketData::from($basket, forStaff: false), $this->baskets->pendingCollections($householdId));
    }
}
