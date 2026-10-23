<?php

use Guixa\Ordering\Domain\Error\NoHousehold;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Tests\Support\Ordering\OrderingWorld;

const MEMBER_ANNA = '00000000-0000-7000-8000-0000000000a1';
const MEMBER_HOUSEHOLD = '00000000-0000-7000-8000-0000000000e1';

beforeEach(function () {
    // Thursday 22 October 2026; the baker closes on Wednesday 4 November at 20:00 for the 7 November pickup.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Loaf', 'loaf', 350, variable: false);
    $this->anna = new Actor(MEMBER_ANNA, [Role::Member], MEMBER_HOUSEHOLD);
    $this->list = fn (?Actor $actor = null) => ($this->world->listMemberCycles)($actor ?? $this->anna);
});

it('lists a cycle as open while a participating producer is open (R4a)', function () {
    $cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER, OrderingWorld::GROWER]);
    $this->world->clock->travelTo('2026-11-04 20:00');

    $cycles = ($this->list)();

    expect($cycles)->toHaveCount(1)
        ->and($cycles[0]->cycleId)->toBe($cycleId)
        ->and($cycles[0]->pickupDate)->toBe('2026-11-07')
        ->and($cycles[0]->open)->toBeTrue();
});

it('lists a cycle as closed to ordering once every participating producer has closed (R4a)', function () {
    ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $this->world->clock->travelTo('2026-11-04 20:00');

    expect(($this->list)()[0]->open)->toBeFalse();
});

it('lists the cycles by pickup date, earliest first (R4)', function () {
    $later = ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::BAKER]);
    $earlier = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);

    expect(array_map(fn ($cycle) => $cycle->cycleId, ($this->list)()))->toBe([$earlier, $later]);
});

it('tells how many lines the household has placed in each cycle before it is settled (R23a)', function () {
    $cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::BAKER]);
    ($this->world->setOrderLine)($this->anna, $cycleId, $this->bread, '2');

    $cycles = ($this->list)();

    expect($cycles[0]->lineCount)->toBe(1)
        ->and($cycles[1]->lineCount)->toBe(0);
});

it('does not count the lines of another household (R7)', function () {
    $cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    ($this->world->setOrderLine)($this->anna, $cycleId, $this->bread, '2');
    $other = new Actor('00000000-0000-7000-8000-0000000000a2', [Role::Member], '00000000-0000-7000-8000-0000000000e2');

    expect(($this->list)($other)[0]->lineCount)->toBe(0);
});

it('refuses an account without a household', function () {
    expect(fn () => ($this->list)(new Actor(MEMBER_ANNA, [Role::Member])))->toThrow(NoHousehold::class);
});
