<?php

use Tests\Support\Membership\MembershipWorld;

beforeEach(function () {
    $this->world = new MembershipWorld;
});

it('answers the name of each account asked for, a deactivated one included (R28a)', function () {
    $managerId = $this->world->addManager('Maria Manager', 'maria@example.org');
    $deactivatedId = $this->world->addManager('Dan Departed', 'dan@example.org');
    ($this->world->deactivateAccount)($this->world->manager, $deactivatedId);

    $names = ($this->world->getAccountNames)([$managerId, $deactivatedId, $managerId]);

    expect($names)->toBe([$managerId => 'Maria Manager', $deactivatedId => 'Dan Departed']);
});

it('leaves out an account it does not know (R28a)', function () {
    expect(($this->world->getAccountNames)(['00000000-0000-7000-8000-0000000000ff']))->toBe([]);
});
