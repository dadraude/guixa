<?php

namespace Tests\Integration\Shared;

use Guixa\Shared\Application\Locks;
use Guixa\Shared\Infrastructure\PostgresLocks;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PostgresLocksTest extends TestCase
{
    #[Test]
    public function it_holds_the_membership_lock_until_the_transaction_ends(): void
    {
        $locks = new PostgresLocks;

        DB::transaction(function () use ($locks): void {
            $locks->acquire(Locks::MEMBERSHIP_CHANGES);

            $this->assertSame(1, $this->heldMembershipLocks());
        });

        $this->assertSame(0, $this->heldMembershipLocks());
    }

    #[Test]
    public function it_can_be_acquired_again_by_the_same_transaction(): void
    {
        $locks = new PostgresLocks;

        DB::transaction(function () use ($locks): void {
            $locks->acquire(Locks::MEMBERSHIP_CHANGES);
            $locks->acquire(Locks::MEMBERSHIP_CHANGES);

            $this->assertSame(1, $this->heldMembershipLocks());
        });
    }

    #[Test]
    public function it_rejects_a_lock_it_does_not_know(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PostgresLocks)->acquire('unknown');
    }

    private function heldMembershipLocks(): int
    {
        return DB::table('pg_locks')
            ->where('locktype', 'advisory')
            ->where('classid', 191983)
            ->where('objid', 1)
            ->whereRaw('pid = pg_backend_pid()')
            ->count();
    }
}
