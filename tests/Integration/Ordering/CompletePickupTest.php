<?php

namespace Tests\Integration\Ordering;

use App\Models\User;
use DomainException;
use Guixa\Ordering\Application\CompletePickup;
use Guixa\Ordering\Domain\Error\NotEnoughStock;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The concurrent calls run in two processes, so the rows must be committed: no wrapping transaction. */
final class CompletePickupTest extends TestCase
{
    use RefreshDatabase;

    protected array $connectionsToTransact = [];

    /** @var resource */
    private $other;

    protected function tearDown(): void
    {
        foreach (['wallet_movements', 'stock_movements', 'order_stock_lines', 'order_lines', 'orders', 'cycle_producers', 'cycles', 'stock_products', 'households', 'users', 'products', 'producers'] as $table) {
            DB::table($table)->delete();
        }

        parent::tearDown();
    }

    #[Test]
    public function a_failure_while_selling_stock_rolls_back_the_top_up_the_charge_and_the_stock_already_sold(): void
    {
        [$manager, $cycle, $household, $order] = $this->pickup();
        $honey = $this->stockProduct('Honey', '5');
        $cheese = $this->stockProduct('Cheese', '1');
        // Sold in stock product id order: whichever sorts first is sold before the one that fails.
        [$first, $second] = $this->sorted($honey, $cheese);
        $this->stockLine($order, $first, '2');
        $this->stockLine($order, $second, '2');
        DB::table('stock_movements')->where('stock_product_id', $second)->update(['quantity' => '1']);
        DB::table('stock_movements')->where('stock_product_id', $first)->update(['quantity' => '5']);

        try {
            app(CompletePickup::class)($manager, $cycle, $household, 4000);
            $this->fail('The completion should have been rejected.');
        } catch (NotEnoughStock) {
            // expected
        }

        $this->assertSame(0, DB::table('wallet_movements')->count());
        $this->assertSame(0, DB::table('stock_movements')->where('type', 'sale')->count());
        $this->assertSame('pending', DB::table('orders')->where('id', $order)->value('pickup_status'));
        $this->assertNull(DB::table('orders')->where('id', $order)->value('charged_cents'));
    }

    #[Test]
    public function a_rejected_top_up_rolls_back_the_stock_already_sold(): void
    {
        [$manager, $cycle, $household, $order] = $this->pickup();
        $this->stockLine($order, $this->stockProduct('Honey', '5'), '2');

        try {
            app(CompletePickup::class)($manager, $cycle, $household, 0);
            $this->fail('The completion should have been rejected.');
        } catch (DomainException) {
            // expected: a top-up must be above zero
        }

        $this->assertSame(0, DB::table('stock_movements')->where('type', 'sale')->count());
        $this->assertSame(0, DB::table('wallet_movements')->count());
        $this->assertSame('pending', DB::table('orders')->where('id', $order)->value('pickup_status'));
    }

    #[Test]
    public function two_concurrent_completions_charge_once(): void
    {
        [$manager, $cycle, $household] = $this->pickup();

        DB::beginTransaction();
        app(CompletePickup::class)($manager, $cycle, $household);
        $pipes = $this->startCompleting($manager->accountId, $cycle, $household);
        $this->waitUntilAnotherSessionWaitsForALock();
        DB::commit();

        $this->assertSame('PickupNotPending', $this->finish($pipes));
        $this->assertSame([-1400], DB::table('wallet_movements')->pluck('amount_cents')->map(fn ($cents) => (int) $cents)->all());
    }

    /** Proves both pickups complete, not the sale order: the ascending order is pinned by the Application test with a recording seller. */
    #[Test]
    public function two_pickups_selling_the_same_stock_products_added_in_opposite_order_both_complete(): void
    {
        [$manager, $cycle, $household, $order] = $this->pickup();
        [$otherHousehold, $otherOrder] = $this->secondPickup($cycle);
        [$first, $second] = $this->sorted($this->stockProduct('Honey', '10'), $this->stockProduct('Cheese', '10'));
        $this->stockLine($order, $first, '1');
        $this->stockLine($order, $second, '1');
        $this->stockLine($otherOrder, $second, '1');
        $this->stockLine($otherOrder, $first, '1');

        DB::beginTransaction();
        app(CompletePickup::class)($manager, $cycle, $household);
        $pipes = $this->startCompleting($manager->accountId, $cycle, $otherHousehold);
        $this->waitUntilAnotherSessionWaitsForALock();
        DB::commit();

        $this->assertSame('completed', $this->finish($pipes));
        $this->assertSame(['completed', 'completed'], DB::table('orders')->orderBy('id')->pluck('pickup_status')->all());
        $this->assertSame(4, DB::table('stock_movements')->where('type', 'sale')->count());
    }

    /** @return array{Actor, string, string, string} manager, settled cycle, household and the household's pending pickup of 4 loaves at 3.50 */
    private function pickup(): array
    {
        $manager = User::factory()->create()->id;
        $household = $this->household();
        $producer = (string) Str::uuid7();
        DB::table('producers')->insertOrIgnore(['id' => $producer, 'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $product = (string) Str::uuid7();
        DB::table('products')->insert(['id' => $product, 'producer_id' => $producer, 'name' => 'Loaf', 'unit' => 'loaf', 'price_cents' => 350, 'variable' => false, 'active' => true]);
        $cycle = (string) Str::uuid7();
        DB::table('cycles')->insert(['id' => $cycle, 'pickup_date' => now()->addDays(2)->toDateString(), 'settled_at' => now()]);
        $order = $this->order($cycle, $household, $manager, $producer, $product);

        return [new Actor($manager, [Role::Manager]), $cycle, $household, $order];
    }

    /** @return array{string, string} household and order, with a loaf pickup like the first one */
    private function secondPickup(string $cycle): array
    {
        $household = $this->household();
        $manager = (string) DB::table('users')->value('id');
        $line = DB::table('order_lines')->first();

        return [$household, $this->order($cycle, $household, $manager, $line->producer_id, $line->product_id)];
    }

    private function household(): string
    {
        $id = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $id, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function order(string $cycle, string $household, string $manager, string $producer, string $product): string
    {
        $order = (string) Str::uuid7();
        DB::table('orders')->insert(['id' => $order, 'cycle_id' => $cycle, 'household_id' => $household, 'pickup_status' => 'pending']);
        DB::table('order_lines')->insert([
            'id' => (string) Str::uuid7(), 'order_id' => $order, 'product_id' => $product, 'producer_id' => $producer, 'product_name' => 'Loaf',
            'unit' => 'loaf', 'unit_price_cents' => 350, 'variable' => false, 'ordered_quantity' => '4', 'allocated_quantity' => '4',
            'created_at' => now(), 'added_by' => $manager,
        ]);

        return $order;
    }

    private function stockProduct(string $name, string $available): string
    {
        $id = (string) Str::uuid7();
        DB::table('stock_products')->insert(['id' => $id, 'name' => $name, 'unit' => 'jar', 'price_cents' => 600, 'variable' => false, 'active' => true]);
        DB::table('stock_movements')->insert([
            'id' => (string) Str::uuid7(), 'stock_product_id' => $id, 'type' => 'entry', 'quantity' => $available,
            'occurred_at' => now(), 'recorded_by' => (string) DB::table('users')->value('id'),
        ]);

        return $id;
    }

    private function stockLine(string $order, string $stockProduct, string $quantity): void
    {
        DB::table('order_stock_lines')->insert([
            'id' => (string) Str::uuid7(), 'order_id' => $order, 'stock_product_id' => $stockProduct, 'name' => 'Stock', 'unit' => 'jar',
            'unit_price_cents' => 600, 'variable' => false, 'quantity' => $quantity,
        ]);
    }

    /** @return array{string, string} */
    private function sorted(string $a, string $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    /** @return array<int, resource> */
    private function startCompleting(string $manager, string $cycle, string $household): array
    {
        $this->other = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2).'/Support/Scripts/complete_pickup.php', $manager, $cycle, $household],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            // Under `--parallel` each worker has its own database; the child must use this one.
            [...getenv(), 'DB_DATABASE' => DB::connection()->getDatabaseName()],
        );

        return $pipes;
    }

    /** @param  array<int, resource>  $pipes */
    private function finish(array $pipes): string
    {
        $result = stream_get_contents($pipes[1]);
        proc_close($this->other);

        return $result;
    }

    /** Proves the other process reached the lock, however slowly it booted: committing earlier would prove nothing. */
    private function waitUntilAnotherSessionWaitsForALock(): void
    {
        $deadline = microtime(true) + 30;

        while (microtime(true) < $deadline) {
            // Inside a transaction Postgres serves one cached snapshot of the statistics, so drop it before each look.
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = DB::selectOne(
                "select count(*) as total from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock' and pid <> pg_backend_pid()",
            );

            if ($waiting->total > 0) {
                return;
            }

            usleep(50_000);
        }

        DB::rollBack();
        proc_terminate($this->other);
        $this->fail('The second call never waited for a lock.');
    }
}
