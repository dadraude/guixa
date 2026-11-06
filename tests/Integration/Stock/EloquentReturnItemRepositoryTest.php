<?php

namespace Tests\Integration\Stock;

use Guixa\Stock\Domain\ReturnItemRepository;
use Guixa\Stock\Infrastructure\Persistence\EloquentReturnItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Contracts\ReturnItemRepositoryContract;
use Tests\TestCase;

final class EloquentReturnItemRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use ReturnItemRepositoryContract;

    protected function repository(): ReturnItemRepository
    {
        return new EloquentReturnItemRepository;
    }
}
