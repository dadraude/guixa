<?php

namespace Guixa\Sales\Domain\ValueObject;

final readonly class StockProductSnapshot
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
