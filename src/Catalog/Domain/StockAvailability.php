<?php

namespace Guixa\Catalog\Domain;

interface StockAvailability
{
    /** @param list<string> $productIds
     * @return array<string, string>
     */
    public function forProducts(array $productIds): array;
}
