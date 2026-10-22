<?php

namespace Tests\Unit\Support;

use Guixa\Stock\Domain\ReturnItemRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\ReturnItemRepositoryContract;
use Tests\Support\InMemory\InMemoryReturnItemRepository;

final class InMemoryReturnItemRepositoryTest extends TestCase
{
    use ReturnItemRepositoryContract;

    protected function repository(): ReturnItemRepository
    {
        return new InMemoryReturnItemRepository;
    }
}
