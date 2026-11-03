<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class ListMemberBaskets
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /** @return list<BasketData> */
    public function __invoke(Actor $actor): array
    {
        $householdId = $actor->householdId ?? '';
        Gate::forUser($actor)->authorize('memberOf', ['household', $householdId]);

        return array_map(fn ($basket) => BasketData::from($basket, forStaff: false), $this->baskets->listForHousehold($householdId));
    }
}
