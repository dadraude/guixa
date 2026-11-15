<?php

use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\Error\NotAStaffRole;
use Guixa\Membership\Domain\Error\RoleNotHeld;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Shared\Domain\Role;

function accountForRemoval(array $roles, ?DateTimeImmutable $activatedAt = null, ?DateTimeImmutable $deactivatedAt = null): Account
{
    return Account::restore('anna', 'Anna', Email::from('anna@example.org'), $roles, null, null, $activatedAt, $deactivatedAt);
}

it('removes one staff role and keeps the others, also on a deactivated account (R2, R11)', function (Role $role) {
    $account = accountForRemoval([Role::Producer, $role], new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-15'));

    $account->removeRole($role);

    expect($account->roles)->toBe([Role::Producer]);
})->with(Role::staff());

it('rejects a role the account does not hold (R13)', function () {
    expect(fn () => accountForRemoval([Role::Finance])->removeRole(Role::Catalogue))->toThrow(RoleNotHeld::class);
});

it('rejects a role that is not a staff role even when the account holds it (R1)', function (Role $role) {
    $account = accountForRemoval([$role]);

    expect(fn () => $account->removeRole($role))->toThrow(NotAStaffRole::class);
    expect($account->roles)->toBe([$role]);
})->with([Role::Producer, Role::Member]);

it('counts a manager only once the invitation is accepted and the account is not deactivated (R1)', function (array $roles, ?string $activatedAt, ?string $deactivatedAt, bool $expected) {
    $account = accountForRemoval(
        $roles,
        $activatedAt ? new DateTimeImmutable($activatedAt) : null,
        $deactivatedAt ? new DateTimeImmutable($deactivatedAt) : null,
    );

    expect($account->isActiveManager())->toBe($expected);
})->with([
    'accepted manager' => [[Role::Manager], '2026-10-01', null, true],
    'pending or expired invitation' => [[Role::Manager], null, null, false],
    'deactivated manager' => [[Role::Manager], '2026-10-01', '2026-10-15', false],
    'accepted but not a manager' => [[Role::Finance], '2026-10-01', null, false],
]);
