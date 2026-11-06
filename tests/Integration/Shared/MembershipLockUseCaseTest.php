<?php

namespace Tests\Integration\Shared;

use Guixa\Membership\Application\RenameHousehold;
use Guixa\Membership\Domain\Household;
use Guixa\Membership\Infrastructure\Persistence\DatabaseHouseholdRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\Locks;
use Guixa\Shared\Infrastructure\DatabaseTransactionRunner;
use Guixa\Shared\Infrastructure\PostgresLocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MembershipLockUseCaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_write_use_case_takes_the_membership_lock_inside_its_transaction(): void
    {
        $spy = new class(new PostgresLocks) implements Locks
        {
            /** @var list<array{string, int}> */
            public array $acquired = [];

            public function __construct(private readonly Locks $inner) {}

            public function acquire(string $name): void
            {
                $this->inner->acquire($name);
                $this->acquired[] = [$name, DB::transactionLevel()];
            }
        };
        $households = new DatabaseHouseholdRepository($spy);
        $household = Household::create($households->nextId(), 'Casa Roca');
        $households->save($household);

        $baselineLevel = DB::transactionLevel();

        (new RenameHousehold($households, new DatabaseTransactionRunner))(
            Actor::manager('00000000-0000-7000-8000-00000000000a'),
            $household->id,
            'Casa Vila',
        );

        $this->assertSame([Locks::MEMBERSHIP_CHANGES], array_column($spy->acquired, 0));
        $this->assertGreaterThan($baselineLevel, $spy->acquired[0][1]);
        $this->assertSame('Casa Vila', $households->get($household->id)?->name);
    }
}
