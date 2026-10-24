<?php

namespace Guixa\Ordering\Domain;

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
    ) {}
}
