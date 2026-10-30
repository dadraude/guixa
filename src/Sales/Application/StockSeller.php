<?php

namespace Guixa\Sales\Application;

use Guixa\Shared\Application\Actor;

interface StockSeller
{
    public function sell(Actor $actor, string $productId, string $quantity, string $basketId): void;
}
