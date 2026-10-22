<?php

namespace Guixa\Stock\Application;

final readonly class StockProductData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $variable,
        public ?string $containersPerUnit,
        public bool $active,
    ) {}
}
