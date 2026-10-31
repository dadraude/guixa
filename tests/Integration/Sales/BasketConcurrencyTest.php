<?php

namespace Tests\Integration\Sales;

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\AddStockToBasket;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Stock\Application\RecordStockEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A second PHP process uses a separate PostgreSQL session; test rows are committed before it starts. */
final class BasketConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected array $connectionsToTransact = [];

    /** @var resource|null */
    private $other = null;

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if (is_resource($this->other)) {
            proc_terminate($this->other);
            proc_close($this->other);
        }
        DB::statement('TRUNCATE TABLE users, households, producers, products CASCADE');
        parent::tearDown();
    }

    #[Test]
    public function competing_final_units_serialize_and_only_one_basket_validates(): void
    {
        [$actor, $household, $products] = $this->world(['Oil' => '5']);
        $first = $this->draft($actor, $household, [$products['Oil'] => '3']);
        $second = $this->draft($actor, $household, [$products['Oil'] => '3']);

        DB::beginTransaction();
        app(ValidateBasket::class)($actor, $first);
        $pipes = $this->start($actor->accountId, $second);
        $this->waitForLock($pipes);
        DB::commit();

        $this->assertSame('NotEnoughStock', $this->finish($pipes));
        $this->assertSame('draft', DB::table('baskets')->where('id', $second)->value('state'));
        $this->assertSame(1, DB::table('stock_movements')->whereNotNull('basket_id')->count());
        $this->assertSame(1, DB::table('wallet_movements')->whereNotNull('basket_id')->count());
    }

    #[Test]
    public function concurrent_replay_waits_for_the_basket_lock_and_creates_no_second_effect(): void
    {
        [$actor, $household, $products] = $this->world(['Oil' => '2']);
        $id = $this->draft($actor, $household, [$products['Oil'] => '1']);

        DB::beginTransaction();
        app(ValidateBasket::class)($actor, $id);
        $pipes = $this->start($actor->accountId, $id);
        $this->waitForLock($pipes);
        DB::commit();

        $this->assertSame('BasketAlreadyValidated', $this->finish($pipes));
        $this->assertSame(1, DB::table('stock_movements')->where('basket_id', $id)->count());
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function opposite_draft_line_order_uses_the_same_product_lock_order_and_both_complete(): void
    {
        [$actor, $household, $products] = $this->world(['Oil' => '2', 'Rice' => '2']);
        $first = $this->draft($actor, $household, [$products['Oil'] => '1', $products['Rice'] => '1']);
        $second = $this->draft($actor, $household, [$products['Rice'] => '1', $products['Oil'] => '1']);

        DB::beginTransaction();
        app(ValidateBasket::class)($actor, $first);
        $pipes = $this->start($actor->accountId, $second);
        $this->waitForLock($pipes);
        DB::commit();

        $this->assertSame('validated', $this->finish($pipes));
        $this->assertSame(2, DB::table('baskets')->where('state', 'validated')->count());
        $this->assertSame(4, DB::table('stock_movements')->whereNotNull('basket_id')->count());
        $this->assertSame(2, DB::table('wallet_movements')->whereNotNull('basket_id')->count());
    }

    /** @return array{Actor,string,array<string,string>} */
    private function world(array $stock): array
    {
        $user = User::factory()->create();
        $actor = Actor::manager($user->id);
        $household = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $household, 'name' => 'K', 'created_at' => now(), 'updated_at' => now()]);
        $producer = ProducerModel::query()->create(['name' => 'Supplier', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $ids = [];
        foreach ($stock as $name => $quantity) {
            $id = (string) Str::uuid7();
            DB::table('products')->insert(['id' => $id, 'producer_id' => $producer->id, 'stock' => true,
                'name' => $name, 'unit' => 'unit', 'price_cents' => 100,
                'variable' => false, 'active' => true]);
            app(RecordStockEntry::class)($actor, $id, $quantity, null);
            $ids[$name] = $id;
        }

        return [$actor, $household, $ids];
    }

    /** @param array<string,string> $lines */
    private function draft(Actor $actor, string $household, array $lines): string
    {
        $id = app(CreateStockBasket::class)($actor, $household);
        foreach ($lines as $productId => $quantity) {
            app(AddStockToBasket::class)($actor, $id, $productId, $quantity);
        }

        return $id;
    }

    /** @return array<int,resource> */
    private function start(string $accountId, string $basketId): array
    {
        $pipes = [];
        $this->other = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/Support/Sales/validate_basket.php', $accountId, $basketId],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
            [...getenv(), 'DB_DATABASE' => DB::connection()->getDatabaseName()]);
        $this->assertIsResource($this->other);

        return $pipes;
    }

    /** @param array<int,resource> $pipes */
    private function waitForLock(array $pipes): void
    {
        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline) {
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock' and pid <> pg_backend_pid()");
            if ($waiting->total > 0) {
                return;
            }
            if (! proc_get_status($this->other)['running']) {
                break;
            }
            usleep(50_000);
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        $this->fail('Second session did not wait for a lock: '.$output.' '.$error);
    }

    /** @param array<int,resource> $pipes */
    private function finish(array $pipes): string
    {
        $output = stream_get_contents($pipes[1]);
        proc_close($this->other);
        $this->other = null;

        return $output;
    }
}
