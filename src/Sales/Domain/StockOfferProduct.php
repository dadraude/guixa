<?php

namespace Guixa\Sales\Domain;

final readonly class StockOfferProduct
{
    public function __construct(
        public string $id,
        public string $producerId,
        public string $producerName,
        public string $name,
        public string $unit,
        public int $priceCents,
    ) {}
}
