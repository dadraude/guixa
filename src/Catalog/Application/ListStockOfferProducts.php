<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;

final class ListStockOfferProducts
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProducerRepository $producers,
    ) {}

    /** @return list<StockOfferProductData> */
    public function __invoke(): array
    {
        $products = array_filter($this->products->listStock(), fn (Product $product) => $product->isActive());
        if ($products === []) {
            return [];
        }

        $producerNames = [];
        foreach ($this->producers->list() as $producer) {
            $producerNames[$producer->id] = $producer->name();
        }

        return array_values(array_map(fn (Product $product) => new StockOfferProductData(
            $product->id,
            $product->producerId,
            $producerNames[$product->producerId] ?? '',
            $product->name(),
            $product->unit(),
            $product->price()->cents,
        ), $products));
    }
}
