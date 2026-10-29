<?php

namespace Tests\Integration\Stock;

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\RecordStockEntry;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Infrastructure\Persistence\EloquentStockMovementRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\StockMovementRepositoryContract;
use Tests\TestCase;

final class EloquentStockMovementRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use StockMovementRepositoryContract;

    protected function repository(): StockMovementRepository
    {
        return new EloquentStockMovementRepository;
    }

    protected function stockProductId(): string
    {
        $producer = ProducerModel::query()->create(['name' => 'Beekeeper', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);

        return ProductModel::query()->create([
            'producer_id' => $producer->id, 'stock' => true,
            'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 650, 'variable' => false, 'active' => true,
        ])->id;
    }

    protected function cycleExists(string $cycleId): void
    {
        DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => '2026-11-07']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function insert(array $overrides): void
    {
        DB::table('stock_movements')->insert([
            'id' => (string) Str::uuid7(), 'stock_product_id' => $this->stockProductId(), 'type' => 'entry', 'quantity' => '1.000',
            'occurred_at' => '2026-10-22T10:00:00+00:00', 'recorded_by' => (string) Str::uuid7(),
            'note' => null, 'cycle_id' => null, 'order_id' => null, ...$overrides,
        ]);
    }

    #[Test]
    public function the_database_refuses_an_entry_that_is_not_positive(): void
    {
        $this->assertThrows(fn () => $this->insert(['type' => 'entry', 'quantity' => '-1.000']), QueryException::class);
        $this->assertThrows(fn () => $this->insert(['type' => 'entry', 'quantity' => '0.000']), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_an_adjustment_without_a_note_or_with_a_zero_quantity(): void
    {
        $this->assertThrows(fn () => $this->insert(['type' => 'adjustment', 'quantity' => '-1.000', 'note' => null]), QueryException::class);
        $this->assertThrows(fn () => $this->insert(['type' => 'adjustment', 'quantity' => '-1.000', 'note' => '  ']), QueryException::class);
        $this->assertThrows(fn () => $this->insert(['type' => 'adjustment', 'quantity' => '0.000', 'note' => 'why']), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_a_sale_that_is_not_negative_or_has_no_order(): void
    {
        $this->assertThrows(fn () => $this->insert(['type' => 'sale', 'quantity' => '1.000', 'order_id' => (string) Str::uuid7()]), QueryException::class);
        $this->assertThrows(fn () => $this->insert(['type' => 'sale', 'quantity' => '-1.000', 'order_id' => null]), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_an_unknown_type_and_an_unknown_stock_product(): void
    {
        $this->assertThrows(fn () => $this->insert(['type' => 'gift']), QueryException::class);
        $this->assertThrows(fn () => $this->insert(['stock_product_id' => (string) Str::uuid7()]), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_a_movement_of_an_unknown_cycle(): void
    {
        $this->assertThrows(
            fn () => $this->insert(['type' => 'adjustment', 'quantity' => '-1.000', 'note' => 'x', 'cycle_id' => (string) Str::uuid7()]),
            QueryException::class,
        );
    }

    #[Test]
    public function the_database_accepts_a_movement_of_an_existing_cycle(): void
    {
        $cycleId = (string) Str::uuid7();
        DB::table('cycles')->insert(['id' => $cycleId, 'pickup_date' => '2026-11-07']);
        $this->insert(['type' => 'adjustment', 'quantity' => '-1.000', 'note' => 'x', 'cycle_id' => $cycleId]);

        $this->assertSame(1, DB::table('stock_movements')->where('cycle_id', $cycleId)->count());
    }

    #[Test]
    public function a_stock_entry_locks_the_catalog_product_before_summing_and_inserting_a_movement(): void
    {
        $repository = $this->repository();
        $productId = $this->stockProductId();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(RecordStockEntry::class)(Actor::manager((string) Str::uuid7()), $productId, '1', null);

        $log = DB::getQueryLog();
        $queries = array_column($log, 'query');
        DB::disableQueryLog();

        $lock = array_find_key($queries, fn (string $sql) => str_contains($sql, 'from "products"') && str_ends_with(trim($sql), 'for update'));
        $sum = array_find_key($queries, fn (string $sql) => str_contains($sql, 'sum(quantity * 1000)'));
        $insert = array_find_key($queries, fn (string $sql) => str_contains($sql, 'insert into "stock_movements"'));

        $this->assertNotNull($lock, 'No Catalog product row lock was issued.');
        $this->assertNotNull($sum);
        $this->assertNotNull($insert);
        $this->assertLessThan($sum, $lock);
        $this->assertLessThan($insert, $sum);
        $this->assertContains($productId, $log[$lock]['bindings']);
        $this->assertSame('1.000', $repository->available($productId)->toString());
    }
}
