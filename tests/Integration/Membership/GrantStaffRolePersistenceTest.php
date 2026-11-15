<?php

namespace Tests\Integration\Membership;

use DateTimeImmutable;
use Guixa\Membership\Application\GrantStaffRole;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FrozenClock;
use Tests\TestCase;

final class GrantStaffRolePersistenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function granting_catalogue_writes_the_role_and_one_change_row(): void
    {
        $accounts = app(AccountRepository::class);
        $this->app->instance(Clock::class, new FrozenClock('2026-11-09 10:00:00', 'UTC'));
        foreach (['manager' => 'Maria', 'anna' => 'Anna'] as $key => $name) {
            $id = $accounts->nextId();
            $ids[$key] = $id;
            $accounts->save(Account::restore($id, $name, Email::from("{$key}@example.org"), [], null, null, new DateTimeImmutable, null));
        }

        app(GrantStaffRole::class)(Actor::manager($ids['manager']), $ids['anna'], Role::Catalogue->value);

        $this->assertSame(
            ['catalogue'],
            DB::table('account_roles')->where('user_id', $ids['anna'])->pluck('role')->all(),
        );
        $changes = DB::table('role_changes')->get();
        $this->assertCount(1, $changes);
        $this->assertSame($ids['anna'], $changes[0]->user_id);
        $this->assertSame('catalogue', $changes[0]->role);
        $this->assertSame('granted', $changes[0]->change);
        $this->assertSame($ids['manager'], $changes[0]->actor_id);
        $this->assertSame('2026-11-09 10:00:00', (new DateTimeImmutable($changes[0]->occurred_at))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }
}
