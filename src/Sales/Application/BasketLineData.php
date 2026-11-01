<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketLine;
use LogicException;

final readonly class BasketLineData
{
    public function __construct(public string $productId, public string $name, public string $unit,
        public string $quantity, public int $priceCents, public int $amountCents) {}

    public static function from(BasketLine $line): self
    {
        $productId = $line->productId ?? throw new LogicException('A stock basket line needs a product.');

        return new self($productId, $line->name, $line->unit, $line->quantity->toString(),
            $line->priceCents, $line->amountCents());
    }
}
