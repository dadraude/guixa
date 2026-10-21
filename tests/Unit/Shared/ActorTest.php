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

    expect($actor->roles)->toBe([Role::Member, Role::Manager]);
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

it('answers role checks by name, ignoring unknown names', function () {
    $actor = new Actor(accountId: 'a1', roles: [Role::Member, Role::Manager]);

    expect($actor->hasAnyRole('producer', 'manager'))->toBeTrue()
        ->and($actor->hasAnyRole('producer', 'nonsense'))->toBeFalse()
        ->and($actor->roleNames())->toBe(['member', 'manager']);
});
