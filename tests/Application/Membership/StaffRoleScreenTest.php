<?php

use Guixa\Membership\Application\GrantStaffRole;
use Guixa\Membership\Application\ListStaffRoleHolders;
use Guixa\Membership\Application\RemoveBlockedReason;
use Guixa\Membership\Application\SearchStaffRoleCandidates;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountStatus;
use Guixa\Membership\Domain\Invitation;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryAccountRepository;
use Tests\Support\InMemory\InMemoryHouseholdRepository;
use Tests\Support\InMemory\InMemoryInvitationRepository;
use Tests\Support\InMemory\InMemoryRoleChangeLog;

/** Anna is active, Bernat's invitation is pending, Carla is deactivated holding Receiver, Dora's invitation expired. */
function staffRoleWorld(): object
{
    $invitations = new InMemoryInvitationRepository;
    $accounts = new InMemoryAccountRepository($invitations);
    $clock = new FrozenClock('2026-11-09 10:00:00');
    $accepted = new DateTimeImmutable('2026-10-01 10:00:00');

    $accounts->save(Account::restore('manager', 'Maria', Email::from('maria@example.org'), [Role::Manager], null, null, $accepted, null));
    $accounts->save(Account::restore('anna', 'Anna', Email::from('anna@example.org'), [], null, null, $accepted, null));
    $accounts->save(Account::restore('bernat', 'Bernat', Email::from('bernat@example.org'), [], null, null, null, null));
    $invitations->save(Invitation::issue('bernat', 'bernat-token', new DateTimeImmutable('2026-11-08 10:00:00')));
    $accounts->save(Account::restore('carla', 'Carla', Email::from('carla@example.org'), [Role::Receiver], null, null, $accepted, new DateTimeImmutable('2026-10-15 10:00:00')));
    $accounts->save(Account::restore('dora', 'Dora', Email::from('dora@example.org'), [], null, null, null, null));
    $invitations->save(Invitation::issue('dora', 'dora-token', new DateTimeImmutable('2026-10-20 10:00:00')));

    return (object) [
        'accounts' => $accounts,
        'log' => $log = new InMemoryRoleChangeLog,
        'manager' => Actor::manager('manager'),
        'grant' => new GrantStaffRole($accounts, new InMemoryHouseholdRepository, $log, new ImmediateTransactionRunner, $clock),
        'list' => new ListStaffRoleHolders($accounts, $clock),
        'search' => new SearchStaffRoleCandidates($accounts, $clock),
    ];
}

it('lists the four staff groups in order, each with its holders and their status (R9, R10, S2)', function () {
    $world = staffRoleWorld();

    $groups = ($world->list)($world->manager);

    expect(array_map(fn ($group) => $group->role, $groups))->toBe(Role::staff());
    expect(array_map(fn ($holder) => $holder->name, $groups[0]->holders))->toBe(['Maria']);
    expect($groups[1]->holders)->toBe([]);
    expect($groups[3]->holders)->toHaveCount(1);
    expect($groups[3]->holders[0]->name)->toBe('Carla');
    expect($groups[3]->holders[0]->status)->toBe(AccountStatus::Deactivated);
});

it('marks the manager itself and blocks removing the last active manager (R1, R11)', function () {
    $world = staffRoleWorld();

    $maria = ($world->list)($world->manager)[0]->holders[0];

    expect($maria->isSelf)->toBeTrue();
    expect($maria->canRemove)->toBeFalse();
    expect($maria->removeBlockedReason)->toBe(RemoveBlockedReason::LastActiveManager);
});

it('offers Bernat as pending and Dora as expired for Finance, never Carla (R10, S2)', function () {
    $world = staffRoleWorld();
    $world->accounts->save(Account::restore('carla', 'Carla Receiver', Email::from('carla@example.org'), [Role::Receiver], null, null, new DateTimeImmutable, new DateTimeImmutable));

    $byName = [];
    foreach (($world->search)($world->manager, Role::Finance->value, 'a') as $candidate) {
        $byName[$candidate->name] = $candidate->status;
    }

    expect($byName)->toBe([
        'Anna' => AccountStatus::Active,
        'Bernat' => AccountStatus::InvitationPending,
        'Dora' => AccountStatus::InvitationExpired,
        'Maria' => AccountStatus::Active,
    ]);
});

it('lists the holder under Catalogue and stops offering them in that search once granted (S1, R2a)', function () {
    $world = staffRoleWorld();

    expect(array_map(fn ($c) => $c->name, ($world->search)($world->manager, Role::Catalogue->value, 'Anna')))->toBe(['Anna']);

    ($world->grant)($world->manager, 'anna', Role::Catalogue->value);

    expect(($world->search)($world->manager, Role::Catalogue->value, 'Anna'))->toBe([]);
    expect(array_map(fn ($holder) => $holder->name, ($world->list)($world->manager)[2]->holders))->toBe(['Anna']);
    expect($world->log->entries)->toHaveCount(1);
});

it('finds nothing for an empty query or for a role that is not a staff role (R10)', function () {
    $world = staffRoleWorld();

    expect(($world->search)($world->manager, Role::Finance->value, ''))->toBe([]);
    expect(($world->search)($world->manager, Role::Producer->value, 'anna'))->toBe([]);
    expect(($world->search)($world->manager, 'nope', 'anna'))->toBe([]);
});

it('keeps listing and searching to managers (R9)', function (Closure $call) {
    $world = staffRoleWorld();

    expect(fn () => $call($world, new Actor('finance', [Role::Finance])))->toThrow(AuthorizationException::class);
})->with([
    'list' => [fn ($world, $actor) => ($world->list)($actor)],
    'search' => [fn ($world, $actor) => ($world->search)($actor, Role::Finance->value, 'anna')],
]);
