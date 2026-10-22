<?php

namespace Tests\Integration\Stock;

use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Infrastructure\Persistence\EloquentStockMovementRepository;
use Guixa\Stock\Infrastructure\Persistence\StockProductModel;
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
        return StockProductModel::query()->create([
            'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 650, 'variable' => false, 'active' => true,
        ])->id;
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
    public function available_for_update_locks_the_stock_product_row_before_summing(): void
    {
        $repository = $this->repository();
        $productId = $this->stockProductId();
        DB::enableQueryLog();

        DB::transaction(fn () => $repository->availableForUpdate($productId));

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $lock = array_find_key($queries, fn (string $sql) => str_contains($sql, 'from "stock_products"') && str_ends_with(trim($sql), 'for update'));
        $sum = array_find_key($queries, fn (string $sql) => str_contains($sql, 'sum(quantity * 1000)'));

        $this->assertNotNull($lock, 'No "select … from stock_products … for update" was issued.');
        $this->assertNotNull($sum);
        $this->assertLessThan($sum, $lock);
        $this->assertStringContainsString('"id" = ?', $queries[$lock]);
    }
}
