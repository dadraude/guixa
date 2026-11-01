<?php

namespace Tests\Unit\Sales;

use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\BasketRepositoryContract;
use Tests\Support\InMemory\Sales\InMemoryBasketRepository;

final class InMemoryBasketRepositoryTest extends TestCase
{
    use BasketRepositoryContract;

    protected function basketWorld(): array
    {
        return [new InMemoryBasketRepository, 'manager', 'household', 'product', 'return-a', 'return-b'];
    }
}
