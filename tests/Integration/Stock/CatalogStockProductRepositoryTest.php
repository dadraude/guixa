<?php

namespace Tests\Integration\Stock;

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Stock\Domain\StockProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CatalogStockProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_translates_catalog_stock_identity_producer_and_metadata_for_the_ledger(): void
    {
        $producer = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => false, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $stock = ProductModel::query()->create([
            'producer_id' => $producer->id, 'stock' => true, 'name' => 'Honey', 'unit' => 'jar',
            'price_cents' => 650, 'variable' => false, 'containers_per_unit' => '2.000', 'active' => true,
        ]);
        ProductModel::query()->create([
            'producer_id' => $producer->id, 'stock' => false, 'name' => 'Weekly honey', 'unit' => 'jar',
            'price_cents' => 500, 'variable' => false, 'active' => true,
        ]);

        $repository = app(StockProductRepository::class);
        $found = $repository->get($stock->id);

        $this->assertSame($stock->id, $found?->id);
        $this->assertSame($producer->id, $found?->producerId);
        $this->assertSame('Honey', $found?->name());
        $this->assertSame('jar', $found?->unit());
        $this->assertSame(650, $found?->price()->cents);
        $this->assertSame('2.000', $found?->containersPerUnit()?->toString());
        $this->assertSame([$stock->id], array_map(fn ($product) => $product->id, $repository->list()));
    }

    #[Test]
    public function it_locks_the_catalog_product_row_for_inventory_writes(): void
    {
        $producer = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $stock = ProductModel::query()->create([
            'producer_id' => $producer->id, 'stock' => true, 'name' => 'Honey', 'unit' => 'jar',
            'price_cents' => 650, 'variable' => false, 'active' => true,
        ]);
        $repository = app(StockProductRepository::class);
        DB::enableQueryLog();

        $found = DB::transaction(fn () => $repository->lock($stock->id));

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        $this->assertSame($stock->id, $found?->id);
        $this->assertSame($producer->id, $found?->producerId);
        $this->assertNotEmpty(array_filter($queries, fn (string $sql) => str_contains($sql, 'from "products"') && str_ends_with(trim($sql), 'for update')));
    }
}
