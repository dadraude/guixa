<?php

namespace App\Http;

use Guixa\Sales\Application\BasketLineData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketStockLineRow
{
    public function __construct(
        public string $stockProductId,
        public string $name,
        public string $unit,
        public string $quantity,
        public int $unitPriceCents,
        public int $amountCents,
    ) {}

    public static function from(BasketLineData $line): self
    {
        return new self($line->productId, $line->name, $line->unit, $line->quantity,
            $line->priceCents, $line->amountCents);
    }
}
