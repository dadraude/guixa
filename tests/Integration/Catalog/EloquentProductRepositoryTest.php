<?php

namespace Tests\Integration\Catalog;

use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Catalog\Infrastructure\Persistence\EloquentProductRepository;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\ProductRepositoryContract;
use Tests\TestCase;

final class EloquentProductRepositoryTest extends TestCase
{
    use ProductRepositoryContract;
    use RefreshDatabase;

    protected function repository(): ProductRepository
    {
        return new EloquentProductRepository;
    }

    protected function newProducerId(): string
    {
        return ProducerModel::query()->create([
            'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
        ])->id;
    }

    #[Test]
    public function the_database_refuses_a_negative_price(): void
    {
        $this->assertThrows(fn () => $this->insertRow(['price_cents' => -1]), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_zero_containers_per_unit(): void
    {
        $this->assertThrows(fn () => $this->insertRow(['containers_per_unit' => '0.000']), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_a_product_of_an_unknown_producer(): void
    {
        $this->assertThrows(fn () => $this->insertRow(['producer_id' => (string) Str::uuid7()]), QueryException::class);
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertRow(array $overrides): void
    {
        DB::table('products')->insert([
            'id' => (string) Str::uuid7(), 'producer_id' => $this->newProducerId(), 'name' => 'Bread', 'unit' => 'loaf',
            'price_cents' => 100, 'variable' => false, 'containers_per_unit' => null, 'active' => true, ...$overrides,
        ]);
    }
}
