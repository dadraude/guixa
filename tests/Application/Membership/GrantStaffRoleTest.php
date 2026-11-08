<?php

use Guixa\Membership\Application\GrantStaffRole;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\Error\AccountDeactivated;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\Error\NotAStaffRole;
use Guixa\Membership\Domain\Error\RoleAlreadyHeld;
use Guixa\Membership\Domain\RoleChange;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\FrozenClock;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\InMemory\InMemoryAccountRepository;
use Tests\Support\InMemory\InMemoryHouseholdRepository;
use Tests\Support\InMemory\InMemoryRoleChangeLog;

it('grants Catalogue to an active account and records who granted it and when (R1, R2a, S1)', function () {
    $accounts = new InMemoryAccountRepository;
    $log = new InMemoryRoleChangeLog;
    $accounts->save(Account::restore('anna', 'Anna', Email::from('anna@example.org'), [], null, null, new DateTimeImmutable, null));
    $grant = new GrantStaffRole(
        $accounts, new InMemoryHouseholdRepository, $log, new ImmediateTransactionRunner,
        new FrozenClock('2026-11-09 10:00:00'),
    );

    $grant(Actor::manager('manager'), 'anna', Role::Catalogue->value);

    expect($accounts->get('anna')->roles)->toEqual([Role::Catalogue]);
    expect($log->entries)->toHaveCount(1);
    expect($log->entries[0]['accountId'])->toBe('anna');
    expect($log->entries[0]['role'])->toBe(Role::Catalogue);
    expect($log->entries[0]['change'])->toBe(RoleChange::Granted);
    expect($log->entries[0]['actorId'])->toBe('manager');
    expect($log->entries[0]['at']->format('Y-m-d H:i:s'))->toBe('2026-11-09 10:00:00');
});

function grantWorld(array $roles = [], ?DateTimeImmutable $activatedAt = null, ?DateTimeImmutable $deactivatedAt = null): object
{
    $accounts = new InMemoryAccountRepository;
    $log = new InMemoryRoleChangeLog;
    $accounts->save(Account::restore('anna', 'Anna', Email::from('anna@example.org'), $roles, null, null, $activatedAt, $deactivatedAt));

    return (object) [
        'accounts' => $accounts,
        'log' => $log,
        'grant' => new GrantStaffRole($accounts, new InMemoryHouseholdRepository, $log, new ImmediateTransactionRunner, new FrozenClock('2026-11-09 10:00:00')),
    ];
}

it('grants every staff role to an account whose invitation is pending or expired (R1, S2)', function (Role $role) {
    $world = grantWorld();

    ($world->grant)(Actor::manager('manager'), 'anna', $role->value);

    expect($world->accounts->get('anna')->roles)->toBe([$role]);
    expect($world->log->entries)->toHaveCount(1);
})->with(Role::staff());

it('does not grant a role to a deactivated account and records nothing (R2)', function () {
    $world = grantWorld([], new DateTimeImmutable, new DateTimeImmutable);

    expect(fn () => ($world->grant)(Actor::manager('manager'), 'anna', Role::Catalogue->value))->toThrow(AccountDeactivated::class);

    expect($world->accounts->get('anna')->roles)->toBe([]);
    expect($world->log->entries)->toBe([]);
});

it('rejects a grant that is already held and records nothing more (R13)', function () {
    $world = grantWorld([Role::Catalogue], new DateTimeImmutable);

    expect(fn () => ($world->grant)(Actor::manager('manager'), 'anna', Role::Catalogue->value))->toThrow(RoleAlreadyHeld::class);

    expect($world->accounts->get('anna')->roles)->toBe([Role::Catalogue]);
    expect($world->log->entries)->toBe([]);
});

it('rejects a role that is not a staff role and records nothing (R13)', function (Role $role) {
    $world = grantWorld([], new DateTimeImmutable);

    expect(fn () => ($world->grant)(Actor::manager('manager'), 'anna', $role->value))->toThrow(NotAStaffRole::class);

    expect($world->accounts->get('anna')->roles)->toBe([]);
    expect($world->log->entries)->toBe([]);
})->with([Role::Producer, Role::Member]);

it('rejects a role name that does not exist and records nothing (R13)', function () {
    $world = grantWorld([], new DateTimeImmutable);

    expect(fn () => ($world->grant)(Actor::manager('manager'), 'anna', 'nope'))->toThrow(NotAStaffRole::class);

    expect($world->log->entries)->toBe([]);
});

it('answers an unknown account with account not found and records nothing', function () {
    $world = grantWorld();

    expect(fn () => ($world->grant)(Actor::manager('manager'), 'missing', Role::Finance->value))->toThrow(AccountNotFound::class);
    expect($world->log->entries)->toBe([]);
});

it('lets only a manager grant a staff role (R1)', function (array $roles) {
    $world = grantWorld([], new DateTimeImmutable);

    expect(fn () => ($world->grant)(new Actor('other', $roles), 'anna', Role::Catalogue->value))->toThrow(AuthorizationException::class);

    expect($world->accounts->get('anna')->roles)->toBe([]);
    expect($world->log->entries)->toBe([]);
})->with([[[Role::Finance]], [[Role::Catalogue]], [[Role::Receiver]], [[Role::Producer]], [[]]]);
