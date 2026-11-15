<?php

namespace Guixa\Sales\Infrastructure\Stock;

use Guixa\Sales\Application\StockCorrector;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\CorrectBasketStock;

final class StockBasketCorrector implements StockCorrector
{
    public function __construct(private readonly CorrectBasketStock $correct) {}

    public function correct(Actor $actor, string $productId, string $quantity, string $reason, string $basketId, string $correctionId): void
    {
        ($this->correct)($actor, $productId, $quantity, $reason, $basketId, $correctionId);
    }
}
