<?php

namespace Tests\Integration\Catalog;

use App\Models\User;
use Guixa\Catalog\Application\ChangeProductClassification;
use Guixa\Catalog\Application\DeleteUnusedProduct;
use Guixa\Catalog\Application\UpdateStockProduct;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Ordering\Application\AddUnorderedProduct;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Ordering\Domain\Error\ProductNotOrderable;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\UtcTimestamp;
use Guixa\Stock\Application\RecordStockEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Separate committed connections prove the lock order across public use cases. */
final class ProductUseConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected array $connectionsToTransact = [];

    protected function tearDown(): void
    {
        foreach (['stock_movements', 'received_totals', 'order_lines', 'orders', 'cycle_producers', 'cycles', 'products', 'producers', 'users', 'households'] as $table) {
            DB::table($table)->delete();
        }
        parent::tearDown();
    }

    #[Test]
    public function first_weekly_line_serializes_with_mode_unit_and_deletion_guards(): void
    {
        $index = 0;
        foreach (['classify-stock' => 'ProductUsed', 'delete-weekly' => 'ProductUsed', 'weekly-unit' => 'ProductHasUnsettledLines'] as $action => $expected) {
            [$producer, $product, $cycle, $household, $anna] = $this->weeklyWorld($index++);
            DB::beginTransaction();
            try {
                app(SetOrderLine::class)(Actor::memberOf($anna, $household), $cycle, $product, '1');
                [$child, $pipes] = $this->spawn('catalog_product_action.php', [$action, $product, $producer]);
                $this->waitForLock($child, $pipes);
                DB::commit();
                $this->assertSame($expected, $this->finish($child, $pipes), $action);
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
            $this->assertTrue(ProductModel::query()->whereKey($product)->exists());
            $this->assertFalse(ProductModel::query()->find($product)->stock);
            $this->assertSame('unit', ProductModel::query()->find($product)->unit);
        }
    }

    #[Test]
    public function reception_first_reference_serializes_with_reclassification_and_deletion(): void
    {
        foreach (['classify-stock' => 'ProductUsed', 'delete-weekly' => 'ProductUsed'] as $index => $expected) {
            [$producer, $product, $cycle] = $this->weeklyWorld(count(DB::table('cycles')->get()) + 10);
            DB::table('cycle_producers')->where('cycle_id', $cycle)->where('producer_id', $producer)->update(['sent_at' => UtcTimestamp::toStorage(now())]);
            DB::beginTransaction();
            try {
                app(AddUnorderedProduct::class)(Actor::manager((string) Str::uuid7()), $cycle, $producer, $product, '0');
                [$child, $pipes] = $this->spawn('catalog_product_action.php', [$index, $product, $producer]);
                $this->waitForLock($child, $pipes);
                DB::commit();
                $this->assertSame($expected, $this->finish($child, $pipes), $index);
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
            $this->assertSame(1, DB::table('received_totals')->where('product_id', $product)->count());
            $this->assertFalse(ProductModel::query()->find($product)->stock);
        }
    }

    #[Test]
    public function first_stock_entry_serializes_with_mode_unit_and_deletion_guards(): void
    {
        foreach (['classify-weekly' => 'ProductUsed', 'delete-stock' => 'ProductUsed', 'stock-unit' => 'ProductHasStockUse'] as $action => $expected) {
            [$producer, $product] = $this->stockWorld();
            DB::beginTransaction();
            try {
                app(RecordStockEntry::class)(Actor::manager((string) Str::uuid7()), $product, '1', null);
                [$child, $pipes] = $this->spawn('catalog_product_action.php', [$action, $product, $producer]);
                $this->waitForLock($child, $pipes);
                DB::commit();
                $this->assertSame($expected, $this->finish($child, $pipes), $action);
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
            $this->assertTrue(ProductModel::query()->whereKey($product)->exists());
            $this->assertTrue(ProductModel::query()->find($product)->stock);
            $this->assertSame('unit', ProductModel::query()->find($product)->unit);
        }
    }

    #[Test]
    public function weekly_line_waiting_on_classification_rechecks_mode_and_commits_no_use(): void
    {
        [$producer, $product, $cycle, $household, $anna] = $this->weeklyWorld(0);
        DB::beginTransaction();
        try {
            app(ChangeProductClassification::class)(Actor::manager((string) Str::uuid7()), $product, true);
            [$child, $pipes] = $this->spawn('set_order_line.php', [$anna, $household, $cycle, $product, '1']);
            $this->waitForLock($child, $pipes);
            DB::commit();
            $this->assertSame('ProductNotOrderable', $this->finish($child, $pipes));
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $this->assertSame(0, DB::table('order_lines')->where('product_id', $product)->count());
        $this->assertTrue(ProductModel::query()->find($product)->stock);
    }

    #[Test]
    public function a_reception_write_rejected_after_classification_commits_no_use(): void
    {
        [$producer, $product, $cycle] = $this->weeklyWorld(0);
        DB::table('cycle_producers')->where('cycle_id', $cycle)->where('producer_id', $producer)->update(['sent_at' => UtcTimestamp::toStorage(now())]);
        app(ChangeProductClassification::class)(Actor::manager((string) Str::uuid7()), $product, true);
        $this->assertThrows(
            fn () => app(AddUnorderedProduct::class)(Actor::manager((string) Str::uuid7()), $cycle, $producer, $product, '0'),
            ProductNotOrderable::class,
        );
        $this->assertSame(0, DB::table('received_totals')->where('product_id', $product)->count());
    }

    #[Test]
    public function stock_entry_waiting_on_classification_rechecks_mode_and_commits_no_use(): void
    {
        [$producer, $product] = $this->stockWorld();
        DB::beginTransaction();
        try {
            app(ChangeProductClassification::class)(Actor::manager((string) Str::uuid7()), $product, false);
            [$child, $pipes] = $this->spawn('record_stock_entry.php', [$product]);
            $this->waitForLock($child, $pipes);
            DB::commit();
            $this->assertSame('StockProductNotFound', $this->finish($child, $pipes));
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $this->assertSame(0, DB::table('stock_movements')->where('product_id', $product)->count());
        $this->assertFalse(ProductModel::query()->find($product)->stock);
    }

    #[Test]
    public function activation_commands_wait_for_unused_deletion_and_cannot_restore_a_deleted_product(): void
    {
        foreach (['deactivate-weekly', 'reactivate-weekly', 'deactivate-stock', 'reactivate-stock'] as $action) {
            if (str_ends_with($action, 'weekly')) {
                [$producer, $product] = $this->weeklyWorld(count(DB::table('cycles')->get()) + 40);
                $actor = Actor::producer((string) Str::uuid7(), $producer);
            } else {
                [$producer, $product] = $this->stockWorld();
                $actor = Actor::manager((string) Str::uuid7());
            }
            DB::beginTransaction();
            try {
                app(DeleteUnusedProduct::class)($actor, $product);
                [$child, $pipes] = $this->spawn('catalog_product_action.php', [$action, $product, $producer]);
                $this->waitForLock($child, $pipes);
                DB::commit();
                $this->assertSame('ProductNotFound', $this->finish($child, $pipes), $action);
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
            $this->assertFalse(ProductModel::query()->whereKey($product)->exists(), $action);
        }
    }

    #[Test]
    public function stock_activation_waits_for_new_unit_and_first_stock_use_without_reverting_metadata(): void
    {
        foreach (['deactivate-stock', 'reactivate-stock'] as $action) {
            [$producer, $product] = $this->stockWorld();
            DB::beginTransaction();
            try {
                app(UpdateStockProduct::class)(Actor::manager((string) Str::uuid7()), $producer, $product, 'Honey', 'kg', 350, false, null);
                app(RecordStockEntry::class)(Actor::manager((string) Str::uuid7()), $product, '1', null);
                [$child, $pipes] = $this->spawn('catalog_product_action.php', [$action, $product, $producer]);
                $this->waitForLock($child, $pipes);
                DB::commit();
                $this->assertSame('done', $this->finish($child, $pipes), $action);
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
            $this->assertSame('kg', ProductModel::query()->find($product)->unit, $action);
            $this->assertSame(1, DB::table('stock_movements')->where('product_id', $product)->count());
        }
    }

    /** @return array{string,string,string,string,string} */
    private function weeklyWorld(int $index): array
    {
        $producer = (string) Str::uuid7();
        $product = (string) Str::uuid7();
        $cycle = (string) Str::uuid7();
        $household = (string) Str::uuid7();
        $anna = User::factory()->create()->id;
        DB::table('households')->insert(['id' => $household, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('producers')->insert(['id' => $producer, 'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        DB::table('products')->insert(['id' => $product, 'producer_id' => $producer, 'name' => 'Bread', 'unit' => 'unit', 'price_cents' => 350, 'variable' => false, 'active' => true]);
        DB::table('cycles')->insert(['id' => $cycle, 'pickup_date' => now()->addDays(30 + $index)->toDateString()]);
        DB::table('cycle_producers')->insert(['cycle_id' => $cycle, 'producer_id' => $producer, 'participating' => true, 'closes_at' => null]);

        return [$producer, $product, $cycle, $household, $anna];
    }

    /** @return array{string,string} */
    private function stockWorld(): array
    {
        $producer = (string) Str::uuid7();
        $product = (string) Str::uuid7();
        DB::table('producers')->insert(['id' => $producer, 'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        DB::table('products')->insert(['id' => $product, 'producer_id' => $producer, 'name' => 'Honey', 'unit' => 'unit', 'price_cents' => 350, 'variable' => false, 'active' => true, 'stock' => true]);

        return [$producer, $product];
    }

    /** @return array{resource,array<int,resource>} */
    private function spawn(string $script, array $args): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2).'/Support/Scripts/'.$script, ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
            [...getenv(), 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'PGTZ' => 'UTC'],
        );
        $this->assertIsResource($process);

        return [$process, $pipes];
    }

    /** @param resource $process @param array<int,resource> $pipes */
    private function waitForLock($process, array $pipes): void
    {
        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline) {
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock' and pid <> pg_backend_pid()");
            if ($waiting->total > 0) {
                return;
            }
            if (! proc_get_status($process)['running']) {
                break;
            }
            usleep(50_000);
        }
        $error = stream_get_contents($pipes[2]);
        proc_terminate($process);
        proc_close($process);
        $this->fail('Child did not wait for a product lock: '.$error);
    }

    /** @param resource $process @param array<int,resource> $pipes */
    private function finish($process, array $pipes): string
    {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $this->assertSame('', $error);

        return $output;
    }
}
