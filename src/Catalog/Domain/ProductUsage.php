<?php

namespace Guixa\Catalog\Domain;

/** Product references are derived from retained source records, never a marker on Product. */
interface ProductUsage
{
    public function hasAny(string $productId): bool;

    public function hasStockUse(string $productId): bool;
}
