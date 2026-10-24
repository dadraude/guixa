<?php

namespace Tests\Unit\Ordering;

use Guixa\Ordering\Domain\ReceivedTotalRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\ReceivedTotalRepositoryContract;
use Tests\Support\InMemory\InMemoryReceivedTotalRepository;

final class InMemoryReceivedTotalRepositoryTest extends TestCase
{
    use ReceivedTotalRepositoryContract;

    protected function repository(): ReceivedTotalRepository
    {
        return new InMemoryReceivedTotalRepository;
    }
}
