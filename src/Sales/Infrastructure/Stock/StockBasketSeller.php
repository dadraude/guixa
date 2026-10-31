<?php

namespace Guixa\Sales\Infrastructure\Stock;

use Guixa\Sales\Application\StockSeller;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\SellBasketStock;

final class StockBasketSeller implements StockSeller
{
    public function __construct(private readonly SellBasketStock $sell) {}

    public function sell(Actor $actor, string $productId, string $quantity, string $basketId): void
    {
        ($this->sell)($actor, $productId, $quantity, $basketId);
    }
}
