<?php

namespace Guixa\Stock\Domain;

interface StockProductRepository
{
    public function get(string $id): ?StockProduct;

    /** Lock a Catalog product before making the first stock reference. */
    public function lock(string $id): ?StockProduct;

    /** @return list<StockProduct> ordered by name, then id */
    public function list(): array;
}
