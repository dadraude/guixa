<?php

namespace Tests\Support\InMemory;

use Guixa\Stock\Domain\StockProduct;
use Guixa\Stock\Domain\StockProductRepository;

/** @extends InMemoryRepository<StockProduct> */
final class InMemoryStockProductRepository extends InMemoryRepository implements StockProductRepository
{
    public function add(StockProduct $product): void
    {
        $this->save($product);
    }

    public function get(string $id): ?StockProduct
    {
        return $this->find($id);
    }

    public function list(): array
    {
        $products = $this->all();
        usort($products, fn (StockProduct $a, StockProduct $b) => [mb_strtolower($a->name()), $a->id] <=> [mb_strtolower($b->name()), $b->id]);

        return $products;
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
