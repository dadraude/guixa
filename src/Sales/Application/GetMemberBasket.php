<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\AccountNames;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class GetMemberBasket
{
    private readonly BasketAuthorNames $authors;

    public function __construct(private readonly BasketRepository $baskets, AccountNames $accountNames)
    {
        $this->authors = new BasketAuthorNames($accountNames);
    }

    public function __invoke(Actor $actor, string $basketId): BasketData
    {
        $householdId = $actor->householdId ?? '';
        Gate::forUser($actor)->authorize('memberOf', ['household', $householdId]);

        $basket = $this->baskets->getForHousehold($basketId, $householdId) ?? throw BasketNotFound::withId($basketId);

        return BasketData::from($basket, forStaff: false, authorNames: $this->authors->of($basket));
    }
}
