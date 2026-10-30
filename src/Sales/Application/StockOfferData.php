<?php

namespace Guixa\Sales\Application;

final readonly class StockOfferData
{
    public function __construct(
        public string $id,
        public string $producerId,
        public string $producerName,
        public string $name,
        public string $unit,
        public int $priceCents,
        public string $available,
        public bool $soldOut,
    ) {}
}
