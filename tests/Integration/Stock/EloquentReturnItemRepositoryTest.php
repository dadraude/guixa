<?php

namespace Tests\Integration\Stock;

use Guixa\Stock\Domain\ReturnItemRepository;
use Guixa\Stock\Infrastructure\Persistence\EloquentReturnItemRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function the_database_refuses_an_amount_of_zero(): void
    {
        $this->assertThrows(fn () => DB::table('return_items')->insert([
            'id' => (string) Str::uuid7(), 'name' => 'Jar', 'amount_cents' => 0, 'active' => true,
        ]), QueryException::class);
    }
}
