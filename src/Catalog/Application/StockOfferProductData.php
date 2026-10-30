<?php

namespace Guixa\Catalog\Application;

final readonly class StockOfferProductData
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
