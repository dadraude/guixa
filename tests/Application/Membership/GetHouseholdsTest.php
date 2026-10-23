<?php

use Tests\Support\Membership\MembershipWorld;

beforeEach(function () {
    $this->world = new MembershipWorld;
});

it('answers the name and active status of the households asked for, by id (R13, R16, R19)', function () {
    $active = ($this->world->createHousehold)($this->world->manager, 'Household H');
    $inactive = ($this->world->createHousehold)($this->world->manager, 'Household J');
    $household = $this->world->households->get($inactive);
    $household->deactivate($this->world->clock->now());
    $this->world->households->save($household);

    $found = ($this->world->getHouseholds)([$active, $inactive, $active]);

    expect(array_keys($found))->toBe([$active, $inactive])
        ->and($found[$active]->name)->toBe('Household H')
        ->and($found[$active]->active)->toBeTrue()
        ->and($found[$inactive]->name)->toBe('Household J')
        ->and($found[$inactive]->active)->toBeFalse();
});

it('leaves out a household it does not know (R13)', function () {
    expect(($this->world->getHouseholds)(['00000000-0000-7000-8000-0000000000ff']))->toBe([]);
});
