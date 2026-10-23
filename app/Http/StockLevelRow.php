<?php

namespace App\Http;

use Guixa\Stock\Application\StockLevelData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A stock product with its available quantity, as the stock list shows it; the price is in euros. */
#[TypeScript]
final readonly class StockLevelRow
{
    public function __construct(
        public string $id,
        public string $name,
        public string $unit,
        public string $price,
        public bool $variable,
        public ?string $containersPerUnit,
        public bool $active,
        public string $available,
    ) {}

    public static function from(StockLevelData $level): self
    {
        return new self(
            $level->id,
            $level->name,
            $level->unit,
            EuroAmount::format($level->priceCents),
            $level->variable,
            $level->containersPerUnit,
            $level->active,
            $level->available,
        );
    }
}
