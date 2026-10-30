<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketLine;

final readonly class BasketLineData
{
    public function __construct(public string $productId, public string $name, public string $unit,
        public string $quantity, public int $priceCents, public int $amountCents) {}

    public static function from(BasketLine $line): self
    {
        return new self($line->productId, $line->name, $line->unit, $line->quantity->toString(),
            $line->priceCents, $line->amountCents());
    }
}
