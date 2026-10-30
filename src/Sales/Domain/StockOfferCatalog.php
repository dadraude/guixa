<?php

namespace Guixa\Sales\Domain;

interface StockOfferCatalog
{
    /** @return list<StockOfferProduct> active stock products, regardless of producer status */
    public function active(): array;
}
