<?php

namespace Guixa\Catalog\Application;

final readonly class ProducerStockData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $active,
        public string $available,
    ) {}
}
