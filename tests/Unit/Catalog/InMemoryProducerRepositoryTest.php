<?php

namespace Tests\Unit\Catalog;

use Guixa\Catalog\Domain\ProducerRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\ProducerRepositoryContract;
use Tests\Support\InMemory\InMemoryProducerRepository;

final class InMemoryProducerRepositoryTest extends TestCase
{
    use ProducerRepositoryContract;

    protected function repository(): ProducerRepository
    {
        return new InMemoryProducerRepository;
    }
}
