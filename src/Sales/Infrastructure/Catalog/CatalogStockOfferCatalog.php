<?php

namespace Guixa\Sales\Infrastructure\Catalog;

use Guixa\Catalog\Application\ListStockOfferProducts;
use Guixa\Sales\Domain\StockOfferCatalog;
use Guixa\Sales\Domain\StockOfferProduct;

final class CatalogStockOfferCatalog implements StockOfferCatalog
{
    public function __construct(private readonly ListStockOfferProducts $products) {}

    public function active(): array
    {
        return array_map(fn ($product) => new StockOfferProduct(
            $product->id,
            $product->producerId,
            $product->producerName,
            $product->name,
            $product->unit,
            $product->priceCents,
        ), ($this->products)());
    }
}
