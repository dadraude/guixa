<?php

namespace Tests\Integration\Ordering;

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentOrderRepository;
use Guixa\Shared\Domain\Quantity;
use Guixa\Shared\Domain\UtcTimestamp;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Contracts\OrderRepositoryContract;
use Tests\TestCase;

final class EloquentOrderRepositoryTest extends TestCase
{
    use OrderRepositoryContract;
    use RefreshDatabase;

    protected function repository(): OrderRepository
    {
        return new EloquentOrderRepository;
    }

    protected function referencedRowsExist(): void
    {
        DB::table('cycles')->insertOrIgnore(['id' => self::CYCLE, 'pickup_date' => '2026-11-07']);

        foreach ([self::HOUSEHOLD, self::OTHER_HOUSEHOLD] as $id) {
            DB::table('households')->insertOrIgnore(['id' => $id, 'name' => "Household {$id}", 'created_at' => now(), 'updated_at' => now()]);
        }

        ProducerModel::query()->firstOrCreate(['id' => self::BAKER], [
            'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
        ]);

        foreach ([self::BREAD, self::APPLES] as $id) {
            DB::table('products')->insertOrIgnore([
                'id' => $id, 'producer_id' => self::BAKER, 'name' => "Product {$id}", 'unit' => 'unit', 'price_cents' => 100, 'variable' => false,
            ]);
        }

        if (! User::query()->whereKey(self::ANNA)->exists()) {
            User::factory()->create(['id' => self::ANNA]);
        }
    }

    #[Test]
    public function history_foreign_keys_prevent_deleting_an_order_that_references_a_product(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('1'), $cycle, $repository->nextId(), self::ANNA, $this->madrid('2026-10-22 10:00'));
        $repository->add($order);
        $order->removeLine(self::BREAD, $cycle, $this->madrid('2026-10-22 10:01'));
        $repository->add($order);

        $this->assertThrows(fn () => DB::table('orders')->where('id', $order->id)->delete(), QueryException::class);
    }

    #[Test]
    public function the_database_refuses_a_second_order_for_one_household_and_cycle(): void
    {
        $this->referencedRowsExist();
        $insert = fn () => DB::table('orders')->insert(['id' => $this->repository()->nextId(), 'cycle_id' => self::CYCLE, 'household_id' => self::HOUSEHOLD]);
        $insert();

        $this->assertThrows($insert, QueryException::class);
    }

    #[Test]
    public function a_product_has_no_unsettled_lines_once_its_cycle_is_settled(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $this->madrid('2026-10-22 10:00'));
        $repository->add($order);

        DB::table('cycles')->where('id', self::CYCLE)->update(['settled_at' => UtcTimestamp::toStorage(now())]);

        $this->assertFalse($repository->hasUnsettledLinesOf(self::BREAD));
    }

    #[Test]
    public function the_schema_keeps_no_pickup_stock_return_adjustment_or_charge_ownership_in_ordering(): void
    {
        foreach (['order_stock_lines', 'order_return_lines', 'order_pickup_adjustments'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }

        $this->assertFalse(Schema::hasColumns('orders', ['pickup_status']));
        $this->assertSame([], array_values(array_intersect(Schema::getColumnListing('orders'), ['pickup_status', 'pickup_opened_at', 'pickup_done_at', 'charged_cents'])));
        $this->assertSame([], array_values(array_intersect(Schema::getColumnListing('order_lines'), ['delivered_quantity'])));
    }
}
