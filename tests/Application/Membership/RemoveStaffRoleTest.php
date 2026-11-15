<?php

use Guixa\Membership\Application\ListStaffRoleHolders;
use Guixa\Membership\Application\ReactivateAccount;
use Guixa\Membership\Application\RemoveBlockedReason;
use Guixa\Membership\Application\RemoveStaffRole;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\Error\AccountNotFound;
use Guixa\Membership\Domain\Error\LastManager;
use Guixa\Membership\Domain\Error\NotAStaffRole;
use Guixa\Membership\Domain\Error\RoleNotHeld;
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

/** Dolors is the only manager who accepted; Ernest holds Manager with his invitation pending (S3). */
function pendingManagerWorld(): object
{
    $accounts = new InMemoryAccountRepository;
    $clock = new FrozenClock('2026-11-09 10:00:00');
    $accounts->save(Account::restore('dolors', 'Dolors', Email::from('dolors@example.org'), [Role::Manager], null, null, new DateTimeImmutable('2026-10-01'), null));
    $accounts->save(Account::restore('ernest', 'Ernest', Email::from('ernest@example.org'), [Role::Manager], null, null, null, null));

    return (object) [
        'accounts' => $accounts,
        'log' => $log = new InMemoryRoleChangeLog,
        'dolors' => Actor::manager('dolors'),
        'remove' => new RemoveStaffRole($accounts, new InMemoryHouseholdRepository, $log, new ImmediateTransactionRunner, $clock),
        'list' => new ListStaffRoleHolders($accounts, $clock),
    ];
}

it('rejects removing Manager from the only accepted manager while the other one is pending (R1, S3)', function () {
    $world = pendingManagerWorld();

    expect(fn () => ($world->remove)($world->dolors, 'dolors', Role::Manager->value))->toThrow(LastManager::class);

    expect($world->accounts->get('dolors')->roles)->toEqual([Role::Manager]);
    expect($world->log->entries)->toBe([]);
});

it('disables her remove action with the reason while the other manager is pending (R1, S3)', function () {
    $world = pendingManagerWorld();

    [$dolors, $ernest] = ($world->list)($world->dolors)[0]->holders;

    expect($dolors->canRemove)->toBeFalse();
    expect($dolors->removeBlockedReason)->toBe(RemoveBlockedReason::LastActiveManager);
    expect($ernest->canRemove)->toBeTrue();
    expect($ernest->removeBlockedReason)->toBeNull();
});

it('lets Dolors remove her own Manager role once Ernest has accepted and records it (S3)', function () {
    $world = pendingManagerWorld();
    $ernest = $world->accounts->get('ernest');
    $ernest->acceptInvitation(new DateTimeImmutable('2026-11-01'));
    $world->accounts->save($ernest);

    ($world->remove)($world->dolors, 'dolors', Role::Manager->value);

    expect($world->accounts->get('dolors')->roles)->toBe([]);
    expect($world->log->entries)->toHaveCount(1);
    expect($world->log->entries[0]['accountId'])->toBe('dolors');
    expect($world->log->entries[0]['role'])->toBe(Role::Manager);
    expect($world->log->entries[0]['change'])->toBe(RoleChange::Removed);
    expect($world->log->entries[0]['actorId'])->toBe('dolors');
});

it('removes Receiver from a deactivated account and keeps its remaining roles on reactivation (S2, R2)', function () {
    $world = pendingManagerWorld();
    $world->accounts->save(Account::restore('carla', 'Carla', Email::from('carla@example.org'), [Role::Receiver, Role::Finance], null, null, new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-15')));

    ($world->remove)($world->dolors, 'carla', Role::Receiver->value);
    expect($world->accounts->get('carla')->roles)->toEqual([Role::Finance]);

    (new ReactivateAccount($world->accounts, new InMemoryHouseholdRepository, new ImmediateTransactionRunner))($world->dolors, 'carla');

    $carla = $world->accounts->get('carla');
    expect($carla->isActive())->toBeTrue();
    expect($carla->roles)->toEqual([Role::Finance]);
});

it('tells the second manager removing the same role that it is no longer held and records one change (S9, R13)', function () {
    $world = pendingManagerWorld();
    $world->accounts->save(Account::restore('anna', 'Anna', Email::from('anna@example.org'), [Role::Finance], null, null, new DateTimeImmutable('2026-10-01'), null));

    ($world->remove)($world->dolors, 'anna', Role::Finance->value);

    expect(fn () => ($world->remove)($world->dolors, 'anna', Role::Finance->value))->toThrow(RoleNotHeld::class);
    expect($world->log->entries)->toHaveCount(1);
    expect(array_map(fn ($holder) => $holder->name, ($world->list)($world->dolors)[1]->holders))->toBe([]);
});

it('rejects an unknown account, a role that is not a staff role and a non-manager, recording nothing (R1, R11)', function (Closure $call, string $error) {
    $world = pendingManagerWorld();

    expect(fn () => $call($world))->toThrow($error);
    expect($world->log->entries)->toBe([]);
})->with([
    'unknown account' => [fn ($world) => ($world->remove)($world->dolors, 'nobody', Role::Finance->value), AccountNotFound::class],
    'producer' => [fn ($world) => ($world->remove)($world->dolors, 'dolors', Role::Producer->value), NotAStaffRole::class],
    'finance actor' => [fn ($world) => ($world->remove)(new Actor('f', [Role::Finance]), 'dolors', Role::Manager->value), AuthorizationException::class],
]);

it('removes Finance and keeps the account\'s other roles (R36a, R36c)', function () {
    $world = pendingManagerWorld();
    $world->accounts->save(Account::restore('fin', 'Fin', Email::from('fin@example.org'), [Role::Producer, Role::Finance], null, 'producer', new DateTimeImmutable('2026-10-01'), null));

    ($world->remove)($world->dolors, 'fin', Role::Finance->value);

    expect($world->accounts->get('fin')->roles)->toEqual([Role::Producer]);
});
