<?php

use Guixa\Ordering\Domain\Error\CycleCancelled;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\ProducerNotActive;
use Guixa\Ordering\Domain\Error\ProducerNotInCycle;
use Guixa\Ordering\Domain\Error\ProducerNotParticipating;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\Ordering\OrderingWorld;

beforeEach(function () {
    // Thursday 22 October 2026; pickups on Saturday 7 and 14 November.
    $this->world = new OrderingWorld('2026-10-22 10:00');
    $this->bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Loaf', 'loaf', 350, variable: false);
    $this->cycleA = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $this->cycleB = ($this->world->openCycle)($this->world->manager, '2026-11-14', [OrderingWorld::BAKER]);
    $this->anna = new Actor('00000000-0000-7000-8000-0000000000a1', [Role::Member], '00000000-0000-7000-8000-0000000000e1');
    $this->stop = fn (string $cycleId) => ($this->world->stopParticipating)($this->world->manager, $cycleId, OrderingWorld::BAKER);
    $this->again = fn (string $cycleId) => ($this->world->participateAgain)($this->world->manager, $cycleId, OrderingWorld::BAKER);
    $this->marked = fn (string $cycleId) => collect(($this->world->getHouseholdOrder)($this->anna, $cycleId)->lines)
        ->pluck('notDelivered', 'productId')->all();
    $this->participating = fn (string $cycleId) => collect(($this->world->listCycles)($this->world->manager))
        ->firstWhere('id', $cycleId)->participants[0]->participating;
});

// R6c — stop participating and participate again while the order is not sent

it('marks the lines will not be delivered and refuses new ones while the producer does not participate (R6c)', function () {
    ($this->world->setOrderLine)($this->anna, $this->cycleA, $this->bread, '2');

    ($this->stop)($this->cycleA);

    expect(($this->participating)($this->cycleA))->toBeFalse()
        ->and(($this->marked)($this->cycleA))->toBe([$this->bread => true])
        ->and(fn () => ($this->world->setOrderLine)($this->anna, $this->cycleA, $this->bread, '3'))
        ->toThrow(ProducerNotParticipating::class);
});

it('stops only the chosen cycle (R6c)', function () {
    ($this->world->setOrderLine)($this->anna, $this->cycleB, $this->bread, '1');

    ($this->stop)($this->cycleA);

    expect(($this->participating)($this->cycleB))->toBeTrue()
        ->and(($this->marked)($this->cycleB))->toBe([$this->bread => false]);
});

it('participates again, lifting the mark and keeping the cutoff (R6c)', function () {
    ($this->world->setOrderLine)($this->anna, $this->cycleA, $this->bread, '2');
    $cutoff = ($this->world->listCycles)($this->world->manager)[0]->participants[0]->cutoff;
    ($this->stop)($this->cycleA);

    ($this->again)($this->cycleA);

    expect(($this->participating)($this->cycleA))->toBeTrue()
        ->and(($this->marked)($this->cycleA))->toBe([$this->bread => false])
        ->and(($this->world->listCycles)($this->world->manager)[0]->participants[0]->cutoff)->toBe($cutoff);
});

it('is idempotent in both directions (R6c)', function () {
    ($this->stop)($this->cycleA);
    ($this->stop)($this->cycleA);
    expect(($this->participating)($this->cycleA))->toBeFalse();

    ($this->again)($this->cycleA);
    ($this->again)($this->cycleA);
    expect(($this->participating)($this->cycleA))->toBeTrue();
});

it('is not open for the producer while it does not participate (R6c)', function () {
    ($this->stop)($this->cycleA);

    $participant = ($this->world->listCycles)($this->world->manager)[0]->participants[0];

    expect($participant->open)->toBeFalse();
});

it('rejects a producer that is not in the cycle, an unknown cycle and a cancelled one (R6c)', function () {
    expect(fn () => ($this->world->stopParticipating)($this->world->manager, $this->cycleA, OrderingWorld::GROWER))
        ->toThrow(ProducerNotInCycle::class)
        ->and(fn () => ($this->world->participateAgain)($this->world->manager, $this->cycleA, OrderingWorld::GROWER))
        ->toThrow(ProducerNotInCycle::class)
        ->and(fn () => ($this->stop)('00000000-0000-7000-8000-0000000000fe'))->toThrow(CycleNotFound::class);

    ($this->world->cancelCycle)($this->world->manager, $this->cycleB);

    expect(fn () => ($this->stop)($this->cycleB))->toThrow(CycleCancelled::class)
        ->and(fn () => ($this->again)($this->cycleB))->toThrow(CycleCancelled::class);
});

it('does not let a deactivated producer participate again (R3a, R6c)', function () {
    ($this->stop)($this->cycleA);
    $this->world->rules->deactivate(OrderingWorld::BAKER);

    expect(fn () => ($this->again)($this->cycleA))->toThrow(ProducerNotActive::class)
        ->and(($this->participating)($this->cycleA))->toBeFalse();
});

it('keeps stopping and participating again to managers (R6c)', function () {
    $producer = new Actor('00000000-0000-7000-8000-0000000000aa', [Role::Producer], null, OrderingWorld::BAKER);

    expect(fn () => ($this->world->stopParticipating)($this->world->member(), $this->cycleA, OrderingWorld::BAKER))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->participateAgain)($producer, $this->cycleA, OrderingWorld::BAKER))->toThrow(NotAllowed::class)
        ->and(($this->participating)($this->cycleA))->toBeTrue();
});

// R3a, D13 — what Catalog asks when it deactivates a producer

it('stops the producer in every cycle where it takes part, and only there (R3a, R6c)', function () {
    $cycleC = ($this->world->openCycle)($this->world->manager, '2026-11-21', [OrderingWorld::GROWER]);
    ($this->world->cancelCycle)($this->world->manager, $this->cycleB);

    ($this->world->stopParticipatingWhereNotSent)(OrderingWorld::BAKER);

    $cycles = collect(($this->world->listCycles)($this->world->manager))->keyBy('id');
    $participating = fn (string $cycleId, string $producerId) => collect($cycles[$cycleId]->participants)
        ->firstWhere('producerId', $producerId)->participating;

    expect($participating($this->cycleA, OrderingWorld::BAKER))->toBeFalse()
        ->and($participating($this->cycleB, OrderingWorld::BAKER))->toBeTrue()
        ->and($participating($cycleC, OrderingWorld::GROWER))->toBeTrue();
});

it('keeps the lines of the producer, marked, once it stops everywhere (S50)', function () {
    ($this->world->setOrderLine)($this->anna, $this->cycleA, $this->bread, '2');

    ($this->world->stopParticipatingWhereNotSent)(OrderingWorld::BAKER);

    expect(($this->marked)($this->cycleA))->toBe([$this->bread => true])
        ->and(fn () => ($this->world->setOrderLine)($this->anna, $this->cycleA, $this->bread, '1'))
        ->toThrow(ProducerNotParticipating::class);
});

it('leaves a cycle where the producer already stopped as it was, and an unknown cycle is checked before the producer (R3a, R6c)', function () {
    ($this->stop)($this->cycleA);
    $this->world->rules->deactivate(OrderingWorld::BAKER);

    ($this->world->stopParticipatingWhereNotSent)(OrderingWorld::BAKER);

    expect(($this->participating)($this->cycleA))->toBeFalse()
        ->and(fn () => ($this->again)('00000000-0000-7000-8000-0000000000fe'))->toThrow(CycleNotFound::class);
});
