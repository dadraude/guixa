<?php

namespace Tests\Integration\Ordering;

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentOrderRepository;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        foreach ([self::HONEY, self::CHEESE] as $id) {
            DB::table('products')->insertOrIgnore(['id' => $id, 'producer_id' => self::BAKER, 'stock' => true, 'name' => "Stock {$id}", 'unit' => 'unit', 'price_cents' => 100, 'variable' => false, 'active' => true]);
        }

        DB::table('return_items')->insertOrIgnore(['id' => self::JAR, 'name' => 'Glass jar', 'amount_cents' => 50, 'active' => true]);

        if (! User::query()->whereKey(self::ANNA)->exists()) {
            User::factory()->create(['id' => self::ANNA]);
        }
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
    public function the_database_refuses_a_negative_ordered_quantity(): void
    {
        $this->referencedRowsExist();
        $order = $this->repository()->lockFor(self::CYCLE, self::HOUSEHOLD);

        $this->assertThrows(fn () => DB::table('order_lines')->insert([
            'id' => $this->repository()->nextId(), 'order_id' => $order->id, 'product_id' => self::BREAD, 'producer_id' => self::BAKER,
            'product_name' => 'Loaf', 'unit' => 'unit', 'unit_price_cents' => 100, 'variable' => false,
            'ordered_quantity' => '-1', 'allocated_quantity' => '0', 'created_at' => now(), 'added_by' => self::ANNA,
        ]), QueryException::class);
    }

    #[Test]
    public function a_product_has_no_unsettled_lines_once_its_cycle_is_settled(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $this->madrid('2026-10-22 10:00'));
        $repository->add($order);

        DB::table('cycles')->where('id', self::CYCLE)->update(['settled_at' => now()]);

        $this->assertFalse($repository->hasUnsettledLinesOf(self::BREAD));
    }

    #[Test]
    public function the_database_refuses_a_stock_or_return_line_of_zero(): void
    {
        $this->referencedRowsExist();
        $order = $this->repository()->lockFor(self::CYCLE, self::HOUSEHOLD);

        $this->assertThrows(fn () => DB::table('order_stock_lines')->insert([
            'id' => $this->repository()->nextId(), 'order_id' => $order->id, 'stock_product_id' => self::HONEY, 'name' => 'Honey',
            'unit' => 'jar', 'unit_price_cents' => 650, 'variable' => false, 'quantity' => '0',
        ]), QueryException::class);
        $this->assertThrows(fn () => DB::table('order_return_lines')->insert([
            'id' => $this->repository()->nextId(), 'order_id' => $order->id, 'return_item_id' => self::JAR, 'name' => 'Glass jar',
            'amount_cents' => 50, 'quantity' => 0,
        ]), QueryException::class);
    }
}
