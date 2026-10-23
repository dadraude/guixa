<?php

namespace Tests\Unit\Ordering;

use Guixa\Ordering\Domain\OrderRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\OrderRepositoryContract;
use Tests\Support\InMemory\InMemoryOrderRepository;

final class InMemoryOrderRepositoryTest extends TestCase
{
    use OrderRepositoryContract;

    protected function repository(): OrderRepository
    {
        return new InMemoryOrderRepository;
    }
}
