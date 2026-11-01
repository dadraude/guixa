<?php

namespace Guixa\Sales\Domain;

interface StockQuantities
{
    /** @param list<string> $ids
     * @return array<string, string> Product id to decimal available quantity.
     */
    public function forProducts(array $ids): array;
}
