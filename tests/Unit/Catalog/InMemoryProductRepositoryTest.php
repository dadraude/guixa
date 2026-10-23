<?php

namespace Tests\Unit\Catalog;

use Guixa\Catalog\Domain\ProductRepository;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\ProductRepositoryContract;
use Tests\Support\InMemory\InMemoryProductRepository;

final class InMemoryProductRepositoryTest extends TestCase
{
    use ProductRepositoryContract;

    protected function repository(): ProductRepository
    {
        return new InMemoryProductRepository;
    }

    protected function newProducerId(): string
    {
        return (string) Str::uuid7();
    }
}
