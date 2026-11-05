<?php

namespace Tests\Integration\Wallet;

use App\Models\User;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\RecordCorrection;
use Guixa\Wallet\Application\RecordTopUp;
use Guixa\Wallet\Domain\MovementRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A second PHP process uses a separate PostgreSQL session; test rows are committed before it starts (R30, R32). */
final class WalletConcurrencyTest extends TestCase
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
        DB::statement('TRUNCATE TABLE users, households CASCADE');
        parent::tearDown();
    }

    #[Test]
    public function two_sessions_replaying_one_receipt_correction_leave_exactly_one_movement(): void
    {
        $actor = Actor::manager(User::factory()->create()->id);
        $household = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $household, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);
        $receipt = app(RecordTopUp::class)($actor, $household, 5000, 'cash');
        $identity = (string) Str::uuid7();

        DB::beginTransaction();
        app(RecordCorrection::class)($actor, $household, -5000, 'duplicated', null, $receipt, $identity);
        $pipes = $this->start($actor->accountId, $household, $receipt, $identity);
        $this->waitForLock($pipes);
        DB::commit();

        $this->assertSame('corrected', $this->finish($pipes));
        $this->assertSame(1, DB::table('wallet_movements')->where('id', $identity)->count());
        $this->assertSame(2, DB::table('wallet_movements')->where('household_id', $household)->count());
        $this->assertSame(0, app(MovementRepository::class)->balanceOf($household)->cents);
        $this->assertSame(5000, (int) DB::table('wallet_movements')->where('id', $receipt)->value('amount_cents'));
    }

    /** @return array<int,resource> */
    private function start(string $accountId, string $household, string $receipt, string $identity): array
    {
        $script = <<<'PHP'
        require getcwd().'/vendor/autoload.php';
        $app = require getcwd().'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        try {
            $app->make(\Guixa\Wallet\Application\RecordCorrection::class)(\Guixa\Shared\Application\Actor::manager($argv[1]), $argv[2], -5000, 'duplicated', null, $argv[3], $argv[4]);
            echo 'corrected';
        } catch (\Throwable $error) {
            echo (new \ReflectionClass($error))->getShortName();
        }
        PHP;
        $pipes = [];
        $this->other = proc_open([PHP_BINARY, '-r', $script, '--', $accountId, $household, $receipt, $identity],
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
