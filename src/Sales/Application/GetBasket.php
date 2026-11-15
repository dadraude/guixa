<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\AccountNames;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class GetBasket
{
    private readonly BasketAuthorNames $authors;

    public function __construct(private readonly BasketRepository $baskets, AccountNames $accountNames)
    {
        $this->authors = new BasketAuthorNames($accountNames);
    }

    public function __invoke(Actor $actor, string $basketId): BasketData
    {
        Gate::forUser($actor)->authorize('manage-sales');

        $basket = $this->baskets->get($basketId) ?? throw BasketNotFound::withId($basketId);

        return BasketData::from($basket, authorNames: $this->authors->of($basket));
    }
}
