<?php

namespace Guixa\Stock\Domain;

interface StockProductRepository
{
    public function nextId(): string;

    /** Inserts the product, or replaces the one with the same id. */
    public function add(StockProduct $product): void;

    public function get(string $id): ?StockProduct;

    /** @return list<StockProduct> ordered by name, then id */
    public function list(): array;
}
