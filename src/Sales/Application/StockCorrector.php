<?php

namespace Guixa\Sales\Application;

use Guixa\Shared\Application\Actor;

interface StockCorrector
{
    /** @param  string  $quantity  Signed stock change: positive puts goods back on the shelf. */
    public function correct(Actor $actor, string $productId, string $quantity, string $reason, string $basketId, string $correctionId): void;
}
