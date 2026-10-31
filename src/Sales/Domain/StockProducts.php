<?php

namespace Guixa\Sales\Domain;

use Guixa\Sales\Domain\ValueObject\StockProductSnapshot;

interface StockProducts
{
    /** Lock the Catalog product row before a first reference or a sale. */
    public function lock(string $productId): ?StockProductSnapshot;
}
