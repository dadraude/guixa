<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\ValueObject\ReturnItemOffer;

/** What Ordering asks Stock about a return item when a manager adds it to a pickup (R20a). */
interface ReturnItemCatalog
{
    /** The return item as it is now, deactivated or not, or null when it is unknown. */
    public function find(string $returnItemId): ?ReturnItemOffer;
}
