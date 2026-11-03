<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\Error\RedistributionBlockedByValidatedBasket;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;

/** A reception with what it was read from, so a use case can change the orders it belongs to. */
final readonly class LoadedReception
{
    /**
     * @param  list<Order>  $orders  every order of the cycle
     * @param  array<string, OrderableProduct>  $catalog  the producer's products by id, deactivated ones included
     * @param  array<string, array<string, true>>  $marksByOrder  marked product ids per order id
     */
    public function __construct(
        public Reception $reception,
        public array $orders,
        public array $catalog,
        public array $marksByOrder,
        public WeeklyBasketSnapshot $baskets = new WeeklyBasketSnapshot,
    ) {}

    /**
     * A validated basket is financially frozen, collected or not: after settlement no allocation or received-total change of a
     * product it has a line of may go on, whatever its quantity. Staff correct that basket instead (R18, R23, S63, S89).
     */
    public function assertRedistributable(Cycle $cycle, string $productId): void
    {
        if (! $cycle->isSettled()) {
            return;
        }

        $basketId = $this->baskets->validatedBasketWith($productId);

        if ($basketId !== null) {
            throw RedistributionBlockedByValidatedBasket::forBasket($basketId, $productId);
        }
    }
}
