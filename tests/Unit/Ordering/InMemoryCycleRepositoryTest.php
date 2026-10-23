<?php

namespace Tests\Unit\Ordering;

use Guixa\Ordering\Domain\CycleRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\CycleRepositoryContract;
use Tests\Support\InMemory\InMemoryCycleRepository;

final class InMemoryCycleRepositoryTest extends TestCase
{
    use CycleRepositoryContract;

    protected function repository(): CycleRepository
    {
        return new InMemoryCycleRepository;
    }
}
