<?php

namespace Guixa\Catalog\Infrastructure\Ordering;

use Guixa\Catalog\Domain\ProductUsage;
use Guixa\Ordering\Application\CheckProductUsage as CheckOrderingProductUsage;
use Guixa\Sales\Application\HasProductHistory;
use Guixa\Stock\Application\CheckProductUsage as CheckStockProductUsage;

final class RetainedProductUsage implements ProductUsage
{
    public function __construct(
        private readonly CheckOrderingProductUsage $ordering,
        private readonly CheckStockProductUsage $stock,
        private readonly HasProductHistory $sales,
    ) {}

    public function hasAny(string $productId): bool
    {
        return ($this->ordering)($productId, false) || $this->hasStockUse($productId);
    }

    public function hasStockUse(string $productId): bool
    {
        return ($this->stock)($productId) || ($this->ordering)($productId, true) || ($this->sales)($productId);
    }
}
