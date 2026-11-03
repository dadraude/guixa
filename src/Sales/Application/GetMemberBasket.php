<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class GetMemberBasket
{
    public function __construct(private readonly BasketRepository $baskets) {}

    public function __invoke(Actor $actor, string $basketId): BasketData
    {
        $householdId = $actor->householdId ?? '';
        Gate::forUser($actor)->authorize('memberOf', ['household', $householdId]);

        return BasketData::from($this->baskets->getForHousehold($basketId, $householdId)
            ?? throw BasketNotFound::withId($basketId), forStaff: false);
    }
}
