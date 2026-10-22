<?php

namespace Tests\Integration\Stock;

use Guixa\Stock\Domain\StockProductRepository;
use Guixa\Stock\Infrastructure\Persistence\EloquentStockProductRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\StockProductRepositoryContract;
use Tests\TestCase;

final class EloquentStockProductRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use StockProductRepositoryContract;

    protected function repository(): StockProductRepository
    {
        return new EloquentStockProductRepository;
    }

    #[Test]
    public function the_database_refuses_a_negative_price(): void
    {
        $this->assertThrows(fn () => DB::table('stock_products')->insert([
            'id' => (string) Str::uuid7(), 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => -1,
            'variable' => false, 'containers_per_unit' => null, 'active' => true,
        ]), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_zero_containers_per_unit(): void
    {
        $this->assertThrows(fn () => DB::table('stock_products')->insert([
            'id' => (string) Str::uuid7(), 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 100,
            'variable' => false, 'containers_per_unit' => '0.000', 'active' => true,
        ]), QueryException::class);
    }
}
