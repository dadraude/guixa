<?php

namespace Tests\Unit\Support;

use Guixa\Stock\Domain\StockProductRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\StockProductRepositoryContract;
use Tests\Support\InMemory\InMemoryStockProductRepository;

final class InMemoryStockProductRepositoryTest extends TestCase
{
    use StockProductRepositoryContract;

    protected function repository(): StockProductRepository
    {
        return new InMemoryStockProductRepository;
    }
}
