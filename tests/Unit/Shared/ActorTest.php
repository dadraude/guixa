<?php

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

it('allows a manager to pass the manager check', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Manager]);

    Gate::forUser($actor)->authorize('manage');

    expect($actor->hasRole(Role::Manager))->toBeTrue();
});

it('rejects the manager check for an account without the manager role', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Member], householdId: 'h7');

    Gate::forUser($actor)->authorize('manage');
})->throws(AuthorizationException::class);

it('lets one account hold several roles at once (R36a)', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Member, Role::Manager], householdId: 'h7');

    Gate::forUser($actor)->authorize('manage');
    Gate::forUser($actor)->authorize('memberOf', ['household', 'h7']);

    expect($actor->roles)->toBe([Role::Manager, Role::Member]);
});

it('accepts a member only for their own household', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Member], householdId: 'h7');

    Gate::forUser($actor)->authorize('memberOf', ['household', 'h7']);

    expect(fn () => Gate::forUser($actor)->authorize('memberOf', ['household', 'h8']))->toThrow(AuthorizationException::class);
});

it('rejects a manager who is not a member of the household', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Manager]);

    Gate::forUser($actor)->authorize('memberOf', ['household', 'h7']);
})->throws(AuthorizationException::class);

it('accepts a producer only for their own producer', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Producer], producerId: 'p3');

    Gate::forUser($actor)->authorize('owns', ['producer', 'p3']);

    expect(fn () => Gate::forUser($actor)->authorize('owns', ['producer', 'p4']))->toThrow(AuthorizationException::class);
});

it('drops duplicate roles', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Manager, Role::Manager]);

    expect($actor->roles)->toBe([Role::Manager]);
});

it('is a member of exactly the household it is linked to, whatever stored roles it holds (R35)', function () {
    $manager = new Actor(accountId: 'a1', roles: [Role::Manager], householdId: 'h7');

    Gate::forUser($manager)->authorize('memberOf', ['household', 'h7']);

    expect($manager->hasRole(Role::Member))->toBeTrue()
        ->and($manager->roleNames())->toBe(['manager', 'member'])
        ->and(fn () => Gate::forUser($manager)->authorize('memberOf', ['household', 'h8']))->toThrow(AuthorizationException::class);
});

it('is not a member without a household, even if handed the member role (R35)', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Member, Role::Manager]);

    expect($actor->hasRole(Role::Member))->toBeFalse()
        ->and($actor->roles)->toBe([Role::Manager])
        ->and(fn () => Gate::forUser($actor)->authorize('memberOf', ['household', 'h7']))->toThrow(AuthorizationException::class);
});

it('answers role checks by name, ignoring unknown names', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Manager], householdId: 'h7');

    expect($actor->hasAnyRole('producer', 'manager'))->toBeTrue()
        ->and($actor->hasAnyRole('producer', 'nonsense'))->toBeFalse()
        ->and($actor->roleNames())->toBe(['manager', 'member']);
});

it('builds a manager with no household or producer', function () {
    $actor = Actor::manager('a1');

    Gate::forUser($actor)->authorize('manage');

    expect($actor->accountId)->toBe('a1')
        ->and($actor->roles)->toBe([Role::Manager])
        ->and($actor->householdId)->toBeNull()
        ->and($actor->producerId)->toBeNull();
});

it('builds a member of exactly one household (R35)', function () {
    $actor = Actor::memberOf('a1', 'h7');

    Gate::forUser($actor)->authorize('memberOf', ['household', 'h7']);

    expect($actor->roles)->toBe([Role::Member])
        ->and(fn () => Gate::forUser($actor)->authorize('memberOf', ['household', 'h8']))->toThrow(AuthorizationException::class)
        ->and(fn () => Gate::forUser($actor)->authorize('manage'))->toThrow(AuthorizationException::class);
});

it('builds a producer of exactly one producer', function () {
    $actor = Actor::producer('a1', 'p3');

    Gate::forUser($actor)->authorize('owns', ['producer', 'p3']);

    expect($actor->roles)->toBe([Role::Producer])
        ->and($actor->householdId)->toBeNull()
        ->and(fn () => Gate::forUser($actor)->authorize('owns', ['producer', 'p4']))->toThrow(AuthorizationException::class);
});
