<?php

namespace App\Http;

use Guixa\Sales\Application\StockOfferData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class StockOfferRow
{
    public function __construct(
        public string $id,
        public string $producerId,
        public string $producerName,
        public string $name,
        public string $unit,
        public string $price,
        public string $available,
        public bool $soldOut,
    ) {}

    public static function from(StockOfferData $offer): self
    {
        return new self(
            $offer->id, $offer->producerId, $offer->producerName, $offer->name,
            $offer->unit, EuroAmount::format($offer->priceCents), $offer->available, $offer->soldOut,
        );
    }
}
