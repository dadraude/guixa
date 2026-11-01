<?php

namespace Tests\Integration\Membership;

use App\Models\User;
use DateTimeImmutable;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Membership\Application\GrantFinanceRole;
use Guixa\Membership\Application\RemoveFinanceRole;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\Invitation;
use Guixa\Membership\Domain\InvitationRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\ActorResolver;
use Guixa\Shared\Domain\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Two real PostgreSQL sessions expose stale Account snapshots after Finance changes. */
final class FinanceRoleConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected array $connectionsToTransact = [];

    /** @var resource|null */
    private $child = null;

    protected function tearDown(): void
    {
        if (is_resource($this->child)) {
            proc_terminate($this->child);
            proc_close($this->child);
        }
        DB::table('invitations')->delete();
        DB::table('account_roles')->delete();
        DB::table('users')->delete();
        DB::table('producers')->delete();
        parent::tearDown();
    }

    public static function writers(): array
    {
        return [
            'producer suspension keeps a new Finance grant' => ['suspend', 'grant', true, false],
            'producer restoration keeps Finance revoked' => ['restore', 'revoke', false, true],
            'invitation acceptance keeps a new Finance grant' => ['accept', 'grant', true, false],
            'invitation email change keeps Finance revoked' => ['email', 'revoke', false, false],
        ];
    }

    #[Test]
    #[DataProvider('writers')]
    public function another_existing_account_writer_cannot_restore_or_erase_finance(
        string $writer,
        string $financeAction,
        bool $expectedFinance,
        bool $expectedProducer,
    ): void {
        $accounts = app(AccountRepository::class);
        $producerId = null;
        if (in_array($writer, ['suspend', 'restore'], true)) {
            $producerId = ProducerModel::query()->create([
                'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00',
            ])->id;
        }
        $roles = $financeAction === 'revoke' ? [Role::Finance] : [];
        if ($writer === 'suspend') {
            $roles[] = Role::Producer;
        }
        $pending = in_array($writer, ['accept', 'email'], true);
        $account = Account::restore(
            $accounts->nextId(), 'Fin', Email::from('fin@example.org'), $roles,
            null, $producerId, $pending ? null : new DateTimeImmutable, null,
        );
        $accounts->save($account);
        if ($writer === 'accept') {
            app(InvitationRepository::class)->save(Invitation::issue($account->id, 'concurrency-token', new DateTimeImmutable));
        }

        DB::beginTransaction();
        try {
            $actor = Actor::manager('00000000-0000-7000-8000-00000000000a');
            if ($financeAction === 'grant') {
                app(GrantFinanceRole::class)($actor, $account->id);
            } else {
                app(RemoveFinanceRole::class)($actor, $account->id);
            }
            $this->child = proc_open(
                [PHP_BINARY, '-r', self::childCode(), $writer, $account->id, $producerId ?? ''],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                [...getenv(), 'DB_DATABASE' => DB::connection()->getDatabaseName()],
            );
            $this->assertIsResource($this->child);
            $this->waitUntilChildWaitsForLock();
            DB::commit();
        } catch (\Throwable $error) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $error;
        }

        $result = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        $exit = proc_close($this->child);
        $this->child = null;
        $this->assertSame(0, $exit, $errors);
        $this->assertSame('done', $result, $errors);
        $stored = $accounts->get($account->id);
        $this->assertSame($expectedFinance, in_array(Role::Finance, $stored->roles, true));
        $this->assertSame($expectedProducer, in_array(Role::Producer, $stored->roles, true));
        $resolved = app(ActorResolver::class)->forAccount($account->id);
        $this->assertSame($expectedFinance, $resolved->hasRole(Role::Finance));
        // Simulate the next request from an already verified session; the role gate is under test.
        DB::table('users')->where('id', $account->id)->update(['email_verified_at' => now()]);
        $request = $this->actingAs(User::query()->findOrFail($account->id))->get('/finance/baskets');
        $expectedFinance ? $request->assertOk() : $request->assertForbidden();
    }

    private function waitUntilChildWaitsForLock(): void
    {
        $deadline = microtime(true) + 30;
        while (microtime(true) < $deadline) {
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock' and pid <> pg_backend_pid()");
            if ($waiting->total > 0) {
                return;
            }
            usleep(50_000);
        }
        $this->fail('The other account writer never waited for the Finance transaction.');
    }

    private static function childCode(): string
    {
        return <<<'PHP_CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    match ($argv[1]) {
        'suspend' => $app->make(Guixa\Membership\Application\SuspendProducerRole::class)($argv[3]),
        'restore' => $app->make(Guixa\Membership\Application\RestoreProducerRole::class)($argv[3]),
        'accept' => $app->make(Guixa\Membership\Application\AcceptInvitation::class)('concurrency-token', 'new-password'),
        'email' => $app->make(Guixa\Membership\Application\ChangeInvitationEmail::class)(Guixa\Shared\Application\Actor::manager('00000000-0000-7000-8000-00000000000a'), $argv[2], 'changed@example.org'),
    };
    echo 'done';
} catch (Throwable $error) {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage());
    exit(1);
}
PHP_CODE;
    }
}
