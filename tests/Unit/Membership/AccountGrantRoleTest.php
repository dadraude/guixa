<?php

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\Error\AccountDeactivated;
use Guixa\Membership\Domain\Error\NotAStaffRole;
use Guixa\Membership\Domain\Error\RoleAlreadyHeld;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;

function annaHolding(array $roles = [], ?DateTimeImmutable $activatedAt = null, ?DateTimeImmutable $deactivatedAt = null): Account
{
    return Account::restore('anna', 'Anna', Email::from('anna@example.org'), $roles, null, null, $activatedAt, $deactivatedAt);
}

it('adds a staff role to an active account and keeps the others (R1)', function (Role $role) {
    $account = annaHolding([Role::Producer], new DateTimeImmutable);

    $account->grantRole($role);

    expect($account->roles)->toBe([Role::Producer, $role]);
})->with(Role::staff());

it('accepts an account whose invitation is still pending (R1)', function () {
    $account = annaHolding();

    $account->grantRole(Role::Receiver);

    expect($account->roles)->toBe([Role::Receiver]);
});

it('rejects a role that is not a staff role (R13)', function (Role $role) {
    $account = annaHolding([], new DateTimeImmutable);

    expect(fn () => $account->grantRole($role))->toThrow(NotAStaffRole::class);
    expect($account->roles)->toBe([]);
})->with([Role::Producer, Role::Member]);

it('rejects a deactivated account (R2)', function () {
    $account = annaHolding([], new DateTimeImmutable, new DateTimeImmutable);

    expect(fn () => $account->grantRole(Role::Catalogue))->toThrow(AccountDeactivated::class);
    expect($account->roles)->toBe([]);
});

it('rejects a role the account already holds (R13)', function () {
    $account = annaHolding([Role::Catalogue], new DateTimeImmutable);

    expect(fn () => $account->grantRole(Role::Catalogue))->toThrow(RoleAlreadyHeld::class);
    expect($account->roles)->toBe([Role::Catalogue]);
});
