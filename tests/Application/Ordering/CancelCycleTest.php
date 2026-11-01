<?php

use Guixa\Ordering\Domain\Error\CycleCancelled;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Shared\Application\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Ordering\OrderingWorld;

const CANCEL_ANNA = '00000000-0000-7000-8000-0000000000a1';
const CANCEL_BIEL = '00000000-0000-7000-8000-0000000000a2';
const CANCEL_HOUSEHOLD_H = '00000000-0000-7000-8000-0000000000e1';
const CANCEL_HOUSEHOLD_J = '00000000-0000-7000-8000-0000000000e2';

beforeEach(function () {
    // Thursday 22 October 2026; the pickup weekday is Saturday and cycle A is picked up on Saturday 7 November.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Loaf', 'loaf', 350, variable: false);
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $this->anna = Actor::memberOf(CANCEL_ANNA, CANCEL_HOUSEHOLD_H);
    $this->biel = Actor::memberOf(CANCEL_BIEL, CANCEL_HOUSEHOLD_J);
    ($this->world->setOrderLine)($this->anna, $this->cycleId, $this->bread, '2');
    ($this->world->setOrderLine)($this->biel, $this->cycleId, $this->bread, '1');
    $this->cycle = fn () => collect(($this->world->listCycles)($this->world->manager))->firstWhere('id', $this->cycleId);
});

it('discards the orders of a cancelled cycle and shows it as cancelled (R4b, S37)', function () {
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    expect(($this->cycle)()->cancelled)->toBeTrue()
        ->and($this->world->orders->ofHousehold($this->cycleId, CANCEL_HOUSEHOLD_H))->toBeNull()
        ->and($this->world->orders->ofHousehold($this->cycleId, CANCEL_HOUSEHOLD_J))->toBeNull();
});

it('shows an open cycle as not cancelled (R4b)', function () {
    expect(($this->cycle)()->cancelled)->toBeFalse();
});

it('lets a manager open a new cycle on the pickup date of a cancelled one (R4, R4b)', function () {
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    $newId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);

    expect($newId)->not->toBe($this->cycleId);
});

it('refuses orders and changes to a cancelled cycle (R4b)', function () {
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    expect(fn () => ($this->world->setOrderLine)($this->anna, $this->cycleId, $this->bread, '3'))->toThrow(CycleCancelled::class)
        ->and(fn () => ($this->world->addParticipant)($this->world->manager, $this->cycleId, OrderingWorld::GROWER))->toThrow(CycleCancelled::class)
        ->and(fn () => ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER))->toThrow(CycleCancelled::class)
        ->and(fn () => ($this->world->setCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER, '2026-11-05T12:00'))->toThrow(CycleCancelled::class)
        ->and(fn () => ($this->world->clearCutoff)($this->world->manager, $this->cycleId, OrderingWorld::BAKER))->toThrow(CycleCancelled::class);
});

it('shows no producer as open in a cancelled cycle (R4b)', function () {
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    expect(($this->cycle)()->participants[0]->open)->toBeFalse();
});

it('refuses to cancel a cycle twice (R4b)', function () {
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    expect(fn () => ($this->world->cancelCycle)($this->world->manager, $this->cycleId))->toThrow(CycleCancelled::class);
});

it('refuses to cancel an unknown cycle (R4b)', function () {
    expect(fn () => ($this->world->cancelCycle)($this->world->manager, '00000000-0000-7000-8000-0000000000ff'))
        ->toThrow(CycleNotFound::class);
});

it('lets only a manager cancel a cycle and keeps the orders otherwise (R4b)', function () {
    expect(fn () => ($this->world->cancelCycle)($this->anna, $this->cycleId))->toThrow(AuthorizationException::class)
        ->and(($this->cycle)()->cancelled)->toBeFalse()
        ->and($this->world->orders->ofHousehold($this->cycleId, CANCEL_HOUSEHOLD_H))->not->toBeNull();
});

it('shows a member a cancelled cycle as cancelled with no lines (R4b, S37)', function () {
    expect(($this->world->getHouseholdOrder)($this->anna, $this->cycleId)->cancelled)->toBeFalse();

    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);

    $order = ($this->world->getHouseholdOrder)($this->anna, $this->cycleId);
    expect($order->cancelled)->toBeTrue()
        ->and($order->lines)->toBe([]);
});
