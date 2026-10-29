<?php

namespace Tests\Support\InMemory;

use Guixa\Catalog\Domain\ProductUsage;

final class FakeProductUsage implements ProductUsage
{
    /** @var array<string, true> */
    public array $any = [];

    /** @var array<string, true> */
    public array $stock = [];

    public function hasAny(string $productId): bool
    {
        return isset($this->any[$productId]) || isset($this->stock[$productId]);
    }

    public function hasStockUse(string $productId): bool
    {
        return isset($this->stock[$productId]);
    }
}
