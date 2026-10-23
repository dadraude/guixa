<?php

namespace Tests\Unit\Ordering;

use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\ProductAvailabilityRepositoryContract;
use Tests\Support\InMemory\InMemoryProductAvailabilityRepository;

final class InMemoryProductAvailabilityRepositoryTest extends TestCase
{
    use ProductAvailabilityRepositoryContract;

    protected function repository(): ProductAvailabilityRepository
    {
        return new InMemoryProductAvailabilityRepository;
    }
}
