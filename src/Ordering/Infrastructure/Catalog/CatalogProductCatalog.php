<?php

namespace Guixa\Ordering\Infrastructure\Catalog;

use Guixa\Catalog\Application\GetOrderableProduct;
use Guixa\Catalog\Application\ListOrderableProducts;
use Guixa\Catalog\Application\ListProducerProductsForReception;
use Guixa\Catalog\Application\OrderableProductData;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** Answers Ordering's questions about products through Catalog's use cases (A4). */
final class CatalogProductCatalog implements ProductCatalog
{
    public function __construct(
        private readonly GetOrderableProduct $getOrderableProduct,
        private readonly ListOrderableProducts $listOrderableProducts,
        private readonly ListProducerProductsForReception $listProducerProductsForReception,
    ) {}

    public function orderable(string $productId): ?OrderableProduct
    {
        $product = ($this->getOrderableProduct)($productId);

        return $product === null ? null : $this->translate($product);
    }

    public function orderableOf(string $producerId): array
    {
        return array_map($this->translate(...), ($this->listOrderableProducts)($producerId));
    }

    public function allOf(string $producerId): array
    {
        return array_map($this->translate(...), ($this->listProducerProductsForReception)($producerId));
    }

    private function translate(OrderableProductData $product): OrderableProduct
    {
        return new OrderableProduct(
            $product->id,
            $product->producerId,
            $product->name,
            $product->unit,
            Money::ofCents($product->priceCents),
            $product->variable,
            $product->containersPerUnit === null ? null : Quantity::fromString($product->containersPerUnit),
        );
    }
}
