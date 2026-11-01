<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\Error\BasketNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class GetBasket
{
    public function __construct(private readonly BasketRepository $baskets) {}

    public function __invoke(Actor $actor, string $basketId): BasketData
    {
        Gate::forUser($actor)->authorize('manage-sales');

        return BasketData::from($this->baskets->get($basketId) ?? throw BasketNotFound::withId($basketId));
    }
}
