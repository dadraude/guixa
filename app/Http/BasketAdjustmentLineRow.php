<?php

namespace App\Http;

use Guixa\Sales\Application\BasketAdjustmentLineData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketAdjustmentLineRow
{
    public function __construct(public string $stockProductId, public string $quantityDelta) {}

    public static function from(BasketAdjustmentLineData $line): self
    {
        return new self($line->productId, $line->quantityDelta);
    }
}
