<?php

namespace Tests\Unit\Support;

use Guixa\Stock\Domain\StockMovementRepository;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\StockMovementRepositoryContract;
use Tests\Support\InMemory\InMemoryStockMovementRepository;

final class InMemoryStockMovementRepositoryTest extends TestCase
{
    use StockMovementRepositoryContract;

    protected function repository(): StockMovementRepository
    {
        return new InMemoryStockMovementRepository;
    }

    protected function productId(): string
    {
        return (string) Str::uuid7();
    }

    protected function correctionFor(string $basket): string
    {
        return (string) Str::uuid7();
    }

    protected function basket(): string
    {
        return (string) Str::uuid7();
    }
}
