<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketAdjustmentLine;

final readonly class BasketAdjustmentLineData
{
    public function __construct(public string $productId, public string $quantityDelta) {}

    public static function from(BasketAdjustmentLine $line): self
    {
        return new self($line->productId, $line->quantityDelta());
    }
}
