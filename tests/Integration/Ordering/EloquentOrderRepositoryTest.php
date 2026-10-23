<?php

namespace Tests\Integration\Ordering;

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Infrastructure\Persistence\EloquentOrderRepository;
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
}
