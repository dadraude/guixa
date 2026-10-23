<?php

namespace Guixa\Catalog\Application;

final readonly class ProductData
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
