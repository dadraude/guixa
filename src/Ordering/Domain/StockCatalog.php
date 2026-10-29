<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\ValueObject\StockOffer;

/** What Ordering asks Stock about a stock product when a manager adds it to a pickup (R20). */
interface StockCatalog
{
    /** The stock product as it is now, deactivated or not, or null when it is unknown. */
    public function offer(string $stockProductId): ?StockOffer;

    /** Lock the Catalog stock product before creating a pickup stock reference. */
    public function lockOffer(string $stockProductId): ?StockOffer;
}
