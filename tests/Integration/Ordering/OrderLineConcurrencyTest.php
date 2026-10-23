<?php

namespace Tests\Integration\Ordering;

use App\Models\User;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The two calls run in two processes, so the rows must be committed: no wrapping transaction. */
final class OrderLineConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected array $connectionsToTransact = [];

    /** @var resource */
    private $other;

    protected function tearDown(): void
    {
        foreach (['order_lines', 'orders', 'cycle_producers', 'cycles', 'products', 'producers', 'users', 'households'] as $table) {
            DB::table($table)->delete();
        }

        parent::tearDown();
    }

    #[Test]
    public function two_calls_for_a_household_with_no_order_yet_create_one_order_with_both_lines(): void
    {
        [$anna, $household, $cycle, $products] = $this->world(3);

        $this->setWhileAnotherCallWaits($anna, $household, $cycle, $products[0], $products[1]);

        $this->assertSame(1, DB::table('orders')->where('cycle_id', $cycle)->where('household_id', $household)->count());
        $this->assertEqualsCanonicalizing([$products[0], $products[1]], DB::table('order_lines')->pluck('product_id')->all());
    }

    #[Test]
    public function two_calls_for_a_household_that_already_has_an_order_keep_every_line(): void
    {
        [$anna, $household, $cycle, $products] = $this->world(3);
        app(SetOrderLine::class)(new Actor($anna, [Role::Member], $household), $cycle, $products[2], '1');

        $this->setWhileAnotherCallWaits($anna, $household, $cycle, $products[0], $products[1]);

        $this->assertSame(1, DB::table('orders')->count());
        $this->assertEqualsCanonicalizing($products, DB::table('order_lines')->pluck('product_id')->all());
    }

    /** @return array{string, string, string, list<string>} account, household, cycle and the products of one open producer */
    private function world(int $products): array
    {
        $household = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $household, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);
        $anna = User::factory()->create()->id;
        $producer = (string) Str::uuid7();
        DB::table('producers')->insert(['id' => $producer, 'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $ids = [];

        for ($i = 0; $i < $products; $i++) {
            $ids[] = $id = (string) Str::uuid7();
            DB::table('products')->insert(['id' => $id, 'producer_id' => $producer, 'name' => "Product {$i}", 'unit' => 'unit', 'price_cents' => 300, 'variable' => false, 'active' => true]);
        }

        $cycle = (string) Str::uuid7();
        DB::table('cycles')->insert(['id' => $cycle, 'pickup_date' => now()->addDays(30)->toDateString()]);
        DB::table('cycle_producers')->insert(['cycle_id' => $cycle, 'producer_id' => $producer, 'participating' => true, 'closes_at' => null]);

        return [$anna, $household, $cycle, $ids];
    }

    /**
     * This transaction sets `$first` and commits only after another process started setting `$second`. Without the
     * lock on the order, that process would read the order as it was before and lose a line (or create a second order).
     */
    private function setWhileAnotherCallWaits(string $anna, string $household, string $cycle, string $first, string $second): void
    {
        DB::beginTransaction();
        app(SetOrderLine::class)(new Actor($anna, [Role::Member], $household), $cycle, $first, '2');
        $other = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2).'/Support/Scripts/set_order_line.php', $anna, $household, $cycle, $second, '1'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            // Under `--parallel` each worker has its own database; the child must use this one.
            [...getenv(), 'DB_DATABASE' => DB::connection()->getDatabaseName()],
        );
        $this->other = $other;
        $this->waitUntilAnotherSessionWaitsForALock();
        DB::commit();

        $result = stream_get_contents($pipes[1]);
        proc_close($other);

        $this->assertSame('set', $result);
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
        $this->fail('The second call never waited for the order lock.');
    }
}
