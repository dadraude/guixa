<?php

namespace Tests\Integration\Sales;

use App\Models\User;
use Guixa\Ordering\Application\ReopenProducerOrder;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A second PHP process uses a separate PostgreSQL session; the world is committed before it starts. */
final class WeeklyReceptionRaceTest extends TestCase
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
        DB::statement('TRUNCATE TABLE users, households, producers, products, cycles CASCADE');
        parent::tearDown();
    }

    #[Test]
    public function a_redistribution_waiting_for_the_cycle_lock_reads_the_validated_basket_afterwards_and_changes_nothing(): void
    {
        $week = $this->settledWeek();
        $this->actingAs($week['manager'])->post("/finance/baskets/{$week['baskets']['A']}/validation")->assertRedirect();
        $before = $this->observed();

        DB::beginTransaction();
        app(ReopenProducerOrder::class)(Actor::manager($week['manager']->id), $week['cycle'], $week['producer']);
        $pipes = $this->start('set_received_total.php', [$week['manager']->id, $week['cycle'], $week['producer'], $week['loaf'], '7']);
        $this->waitForLock($pipes);
        DB::commit();

        $this->assertSame('RedistributionBlockedByValidatedBasket', $this->finish($pipes));
        $this->assertSame($before, $this->observed());
    }

    #[Test]
    public function a_validation_waiting_for_the_cycle_lock_is_refused_when_a_reopen_committed_first_and_charges_nothing(): void
    {
        $week = $this->settledWeek();

        DB::beginTransaction();
        app(ReopenProducerOrder::class)(Actor::manager($week['manager']->id), $week['cycle'], $week['producer']);
        $pipes = $this->start('Sales/validate_basket.php', [$week['manager']->id, $week['baskets']['B']]);
        $this->waitForLock($pipes);
        DB::commit();

        $this->assertSame('ReceptionReopened', $this->finish($pipes));
        $this->assertSame('draft', DB::table('baskets')->where('id', $week['baskets']['B'])->value('state'));
        $this->assertSame(0, DB::table('wallet_movements')->count());
    }

    /** @return array{cycle: string, producer: string, loaf: string, manager: User, baskets: array<string, string>} */
    private function settledWeek(): array
    {
        [$cycle, $baker, $loaf] = cycleWithBaker();
        $baskets = [];
        foreach (['A' => '5', 'B' => '2'] as $name => $quantity) {
            $household = (string) Str::uuid7();
            DB::table('households')->insert(['id' => $household, 'name' => "Household {$name}", 'created_at' => now(), 'updated_at' => now()]);
            $this->actingAs(memberOf($household, "Member {$name}"))->put("/cycles/{$cycle}/order/lines/{$loaf}", ['quantity' => $quantity])->assertRedirect();
            app('auth')->forgetGuards();
            $baskets[$name] = $household;
        }
        DB::table('cycle_producers')->where('cycle_id', $cycle)->update(['closes_at' => now()->subHour(), 'sent_at' => now()]);
        $manager = accountWith(Role::Manager);
        $reception = "/receptions/{$cycle}/{$baker->id}";
        $this->actingAs($manager)->put("{$reception}/products/{$loaf}/received", ['received' => '6'])->assertRedirect();
        $this->post("{$reception}/validate")->assertRedirect();
        $this->post("{$reception}/distribution")->assertRedirect();
        foreach ($baskets as $name => $household) {
            $baskets[$name] = (string) DB::table('baskets')->where('household_id', $household)->value('id');
        }

        return ['cycle' => $cycle, 'producer' => $baker->id, 'loaf' => $loaf, 'manager' => $manager, 'baskets' => $baskets];
    }

    /** @return array<string, mixed> */
    private function observed(): array
    {
        return [
            'received' => DB::table('received_totals')->orderBy('product_id')->get()->map(fn ($row) => (array) $row)->all(),
            'allocations' => DB::table('order_lines')->orderBy('id')->pluck('allocated_quantity', 'id')->all(),
            'baskets' => DB::table('baskets')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'lines' => DB::table('basket_lines')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'wallet' => DB::table('wallet_movements')->count(),
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array<int,resource>
     */
    private function start(string $script, array $arguments): array
    {
        $pipes = [];
        $this->other = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/Support/'.(str_contains($script, '/') ? '' : 'Scripts/').$script, ...$arguments],
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
        $this->fail('Second session did not wait for a lock: '.stream_get_contents($pipes[1]).' '.stream_get_contents($pipes[2]));
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
