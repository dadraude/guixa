<?php

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;

it('allows a manager to pass the manager check', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Manager]);

    $actor->assertManager();

    expect($actor->hasRole(Role::Manager))->toBeTrue();
});

it('rejects the manager check for an account without the manager role', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Member], householdId: 'h7');

    $actor->assertManager();
})->throws(NotAllowed::class);

it('lets one account hold several roles at once (R36a)', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Member, Role::Manager], householdId: 'h7');

    $actor->assertManager();
    $actor->assertMemberOf('h7');

    expect($actor->roles)->toBe([Role::Manager, Role::Member]);
});

it('accepts a member only for their own household', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Member], householdId: 'h7');

    $actor->assertMemberOf('h7');

    expect(fn () => $actor->assertMemberOf('h8'))->toThrow(NotAllowed::class);
});

it('rejects a manager who is not a member of the household', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Manager]);

    $actor->assertMemberOf('h7');
})->throws(NotAllowed::class);

it('accepts a producer only for their own producer', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Producer], producerId: 'p3');

    $actor->assertProducerOf('p3');

    expect(fn () => $actor->assertProducerOf('p4'))->toThrow(NotAllowed::class);
});

it('drops duplicate roles', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Manager, Role::Manager]);

    expect($actor->roles)->toBe([Role::Manager]);
});

it('is a member of exactly the household it is linked to, whatever stored roles it holds (R35)', function () {
    $manager = new Actor(accountId: 'a1', roles: [Role::Manager], householdId: 'h7');

    $manager->assertMemberOf('h7');

    expect($manager->hasRole(Role::Member))->toBeTrue()
        ->and($manager->roleNames())->toBe(['manager', 'member'])
        ->and(fn () => $manager->assertMemberOf('h8'))->toThrow(NotAllowed::class);
});

it('is not a member without a household, even if handed the member role (R35)', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Member, Role::Manager]);

    expect($actor->hasRole(Role::Member))->toBeFalse()
        ->and($actor->roles)->toBe([Role::Manager])
        ->and(fn () => $actor->assertMemberOf('h7'))->toThrow(NotAllowed::class);
});

it('answers role checks by name, ignoring unknown names', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Manager], householdId: 'h7');

    expect($actor->hasAnyRole('producer', 'manager'))->toBeTrue()
        ->and($actor->hasAnyRole('producer', 'nonsense'))->toBeFalse()
        ->and($actor->roleNames())->toBe(['manager', 'member']);
});

it('builds a manager with no household or producer', function () {
    $actor = Actor::manager('a1');

    $actor->assertManager();

    expect($actor->accountId)->toBe('a1')
        ->and($actor->roles)->toBe([Role::Manager])
        ->and($actor->householdId)->toBeNull()
        ->and($actor->producerId)->toBeNull();
});

it('builds a member of exactly one household (R35)', function () {
    $actor = Actor::memberOf('a1', 'h7');

    $actor->assertMemberOf('h7');

    expect($actor->roles)->toBe([Role::Member])
        ->and(fn () => $actor->assertMemberOf('h8'))->toThrow(NotAllowed::class)
        ->and(fn () => $actor->assertManager())->toThrow(NotAllowed::class);
});

it('builds a producer of exactly one producer', function () {
    $actor = Actor::producer('a1', 'p3');

    $actor->assertProducerOf('p3');

    expect($actor->roles)->toBe([Role::Producer])
        ->and($actor->householdId)->toBeNull()
        ->and(fn () => $actor->assertProducerOf('p4'))->toThrow(NotAllowed::class);
});
