<?php

namespace App\Http;

use Guixa\Catalog\Application\ProducerStockData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class ProducerStockRow
{
    public function __construct(
        public string $id,
        public string $name,
        public string $unit,
        public string $price,
        public bool $active,
        public string $available,
    ) {}

    public static function from(ProducerStockData $stock): self
    {
        return new self(
            $stock->id, $stock->name, $stock->unit,
            EuroAmount::format($stock->priceCents), $stock->active, $stock->available,
        );
    }
}
