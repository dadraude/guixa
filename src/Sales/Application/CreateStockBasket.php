<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\Error\HouseholdUnavailable;
use Guixa\Sales\Domain\HouseholdDirectory;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Illuminate\Support\Facades\Gate;

final class CreateStockBasket
{
    public function __construct(private readonly BasketRepository $baskets, private readonly HouseholdDirectory $households, private readonly Clock $clock) {}

    public function __invoke(Actor $actor, string $householdId): string
    {
        Gate::forUser($actor)->authorize('manage-sales');
        foreach ($this->households->all() as $household) {
            if ($household->id === $householdId && $household->active) {
                $id = $this->baskets->nextId();
                $this->baskets->save(new Basket($id, $householdId, $household->name, BasketState::Draft, $actor->accountId, $this->clock->now()));

                return $id;
            }
        }

        throw HouseholdUnavailable::create();
    }
}
