<?php

namespace Tests\Integration\Membership;

use App\Models\User;
use Guixa\Membership\Application\RemoveStaffRole;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The two removals run in two processes, so the rows must be committed: no wrapping transaction. */
final class RemoveStaffRoleConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected array $connectionsToTransact = [];

    /** @var resource */
    private $other;

    protected function tearDown(): void
    {
        DB::table('account_roles')->delete();
        DB::table('users')->delete();

        parent::tearDown();
    }

    #[Test]
    public function two_removals_on_the_last_two_accepted_managers_leave_one_manager(): void
    {
        $accounts = app(AccountRepository::class);
        $first = $this->manager($accounts, 'Carla', 'carla@example.org');
        $second = $this->manager($accounts, 'Dan', 'dan@example.org');
        $removeStaffRole = app(RemoveStaffRole::class);
        // The change log keeps the actor as a user, so the removals need a real one.
        $actorId = User::factory()->create()->id;
        $actor = Actor::manager($actorId);

        // This transaction removes the first manager's role and commits only after the other process started
        // its own removal. Without the lock, that process would still see two managers.
        DB::beginTransaction();
        $removeStaffRole($actor, $first->id, 'manager');
        $other = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2).'/Support/Scripts/remove_staff_role.php', $actorId, $second->id, 'manager'],
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

        $this->assertSame('LastManager', $result);
        $this->assertSame([], $accounts->get($first->id)->roles);
        $this->assertSame([Role::Manager], $accounts->get($second->id)->roles);
        $this->assertSame(1, DB::table('role_changes')->count());
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
        $this->fail('The second removal never waited for the manager lock.');
    }

    private function manager(AccountRepository $accounts, string $name, string $email): Account
    {
        $account = Account::restore($accounts->nextId(), $name, Email::from($email), [Role::Manager], null, null, now()->toDateTimeImmutable(), null);
        $accounts->save($account);

        return $account;
    }
}
