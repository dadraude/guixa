<?php

namespace Tests\Integration\Membership;

use Guixa\Membership\Application\AddMember;
use Guixa\Membership\Application\DeactivateHousehold;
use Guixa\Shared\Application\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Separate committed sessions expose the stale reads that one test transaction would hide. */
final class HouseholdDeactivationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    protected array $connectionsToTransact = [];

    /** @var resource */
    private $worker;

    protected function tearDown(): void
    {
        DB::table('invitations')->delete();
        DB::table('account_roles')->delete();
        DB::table('users')->delete();
        DB::table('households')->delete();
        parent::tearDown();
    }

    #[Test]
    public function add_waiting_for_deactivation_reactivates_the_household_before_linking(): void
    {
        $household = $this->household();
        DB::beginTransaction();
        app(DeactivateHousehold::class)($this->manager(), $household);
        $pipes = $this->startWorker('add', $household);
        $this->waitForLock();
        DB::commit();

        $member = $this->finishWorker($pipes);
        $this->assertSame($household, DB::table('users')->where('id', $member)->value('household_id'));
        $this->assertNull(DB::table('households')->where('id', $household)->value('deactivated_at'));
    }

    #[Test]
    public function move_waiting_for_deactivation_reactivates_the_target_before_linking(): void
    {
        $household = $this->household();
        $source = $this->household();
        $member = app(AddMember::class)($this->manager(), $source, 'Anna', 'anna@example.org');
        DB::beginTransaction();
        app(DeactivateHousehold::class)($this->manager(), $household);
        $pipes = $this->startWorker('move', $household, $member);
        $this->waitForLock();
        DB::commit();

        $this->assertSame('moved', $this->finishWorker($pipes));
        $this->assertSame($household, DB::table('users')->where('id', $member)->value('household_id'));
        $this->assertNull(DB::table('households')->where('id', $household)->value('deactivated_at'));
        $this->assertNotNull(DB::table('households')->where('id', $source)->value('deactivated_at'));
    }

    #[Test]
    public function role_save_waiting_for_deactivation_keeps_the_unlinked_account_unlinked(): void
    {
        $household = $this->household();
        $member = app(AddMember::class)($this->manager(), $household, 'Anna', 'anna@example.org');
        DB::beginTransaction();
        app(DeactivateHousehold::class)($this->manager(), $household);
        $pipes = $this->startWorker('grant', $household, $member);
        $this->waitForLock();
        DB::commit();

        $this->assertSame('granted', $this->finishWorker($pipes));
        $this->assertNull(DB::table('users')->where('id', $member)->value('household_id'));
        $this->assertNotNull(DB::table('households')->where('id', $household)->value('deactivated_at'));
        $this->assertTrue(DB::table('account_roles')->where('user_id', $member)->where('role', 'manager')->exists());
    }

    private function household(): string
    {
        $id = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $id, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function manager(): Actor
    {
        return Actor::manager('00000000-0000-7000-8000-00000000000a');
    }

    /** @return array<int, resource> */
    private function startWorker(string $command, string $household, ?string $member = null): array
    {
        $worker = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2).'/Support/Scripts/household_membership.php', $command, $household, $member ?? ''],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [...getenv(), 'DB_DATABASE' => DB::connection()->getDatabaseName()],
        );

        if ($worker === false) {
            throw new \RuntimeException('Could not start the concurrent membership worker.');
        }

        $this->worker = $worker;

        return $pipes;
    }

    /** @param array<int, resource> $pipes */
    private function finishWorker(array $pipes): string
    {
        $result = trim(stream_get_contents($pipes[1]));
        proc_close($this->worker);

        return $result;
    }

    private function waitForLock(): void
    {
        $deadline = microtime(true) + 8;

        while (microtime(true) < $deadline) {
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock' and pid <> pg_backend_pid()");

            if ($waiting->total > 0) {
                return;
            }

            usleep(50_000);
        }

        DB::rollBack();
        proc_terminate($this->worker);
        $this->fail('The concurrent membership command did not wait for a lock.');
    }
}
