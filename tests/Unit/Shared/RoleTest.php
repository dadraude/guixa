<?php

use Guixa\Shared\Domain\Role;

it('lists the staff roles in the order the role screen shows them (R9)', function () {
    expect(Role::staff())->toBe([Role::Manager, Role::Finance, Role::Catalogue, Role::Receiver]);
});

it('knows which roles are staff roles', function (Role $role, bool $staff) {
    expect($role->isStaff())->toBe($staff);
})->with([
    [Role::Manager, true],
    [Role::Finance, true],
    [Role::Catalogue, true],
    [Role::Receiver, true],
    [Role::Producer, false],
    [Role::Member, false],
]);
