<?php

use Guixa\Ordering\Application\CycleSettlement;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Shared\Application\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\InMemory\ImmediateTransactionRunner;
use Tests\Support\Ordering\OrderingWorld;

const CONTAINER_H = '00000000-0000-7000-8000-0000000000e1';
const CONTAINER_K = '00000000-0000-7000-8000-0000000000e2';

beforeEach(function () {
    $this->world = new OrderingWorld;
    $this->world->households->know(CONTAINER_H, 'Household H');
    $this->world->households->know(CONTAINER_K, 'Household K');
    $this->cycleId = ($this->world->openCycle)($this->world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $this->h = Actor::memberOf('00000000-0000-7000-8000-0000000000a1', CONTAINER_H);
    $this->k = Actor::memberOf('00000000-0000-7000-8000-0000000000a2', CONTAINER_K);
    $this->settle = function () {
        $loader = new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals, $this->world->baskets);
        $transactions = new ImmediateTransactionRunner;
        $settlement = new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders), $this->world->baskets);
        ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        (new ValidateReception($this->world->cycles, $transactions, $this->world->clock))($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
        (new MarkProducerDistributed($this->world->cycles, $loader, $settlement, $transactions, $this->world->clock))($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    };
});

it('counts rounded snapshotted weekly quantities per household and cycle (R1, R10, R18a, R25, S60)', function () {
    $yoghurt = $this->world->catalog->offer(OrderingWorld::BAKER, 'Yoghurt', 'kg', 400, true, '2');
    $honey = $this->world->catalog->offer(OrderingWorld::BAKER, 'Honey', 'jar', 600, false, '1');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $yoghurt, '1.300');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $honey, '3');
    ($this->world->setOrderLine)($this->k, $this->cycleId, $yoghurt, '0.500');
    $this->world->catalog->changeContainersPerUnit($yoghurt, '4');
    ($this->settle)();

    $count = ($this->world->getContainerCount)($this->world->manager, $this->cycleId);

    // Stock lines attributed to the cycle by Sales (S60's K) arrive with T60; the weekly snapshots already give H and K.
    expect($count->total)->toBe(7)
        ->and(array_map(fn ($row) => [$row->householdName, $row->count], $count->households))
        ->toBe([['Household H', 6], ['Household K', 1]]);
});

it('uses effective delivered quantities and excludes marked, dropped and containerless lines (R18a)', function () {
    $bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, false, '1');
    $marked = $this->world->catalog->offer(OrderingWorld::BAKER, 'Marked', 'loaf', 300, false, '4');
    $dropped = $this->world->catalog->offer(OrderingWorld::BAKER, 'Dropped', 'loaf', 300, false, '4');
    $plain = $this->world->catalog->offer(OrderingWorld::BAKER, 'Plain', 'loaf', 300, false);
    ($this->world->setOrderLine)($this->h, $this->cycleId, $bread, '2');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $marked, '2');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $dropped, '2');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $plain, '5');
    $this->world->catalog->deactivate($marked);
    $order = $this->world->orders->ofHousehold($this->cycleId, CONTAINER_H);
    $order->drop([$dropped => true]);
    $this->world->orders->add($order);
    ($this->settle)();
    $this->world->baskets->setActual($order->id, $bread, '1');

    expect(($this->world->getContainerCount)($this->world->manager, $this->cycleId)->total)->toBe(1);
});

it('is manager-only, rejects unknown cycles and has no cancelled totals (R18a)', function () {
    expect(fn () => ($this->world->getContainerCount)($this->h, $this->cycleId))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->world->getContainerCount)($this->world->manager, 'missing'))->toThrow(CycleNotFound::class);
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);
    expect(($this->world->getContainerCount)($this->world->manager, $this->cycleId)->total)->toBe(0);
});

it('uses the effective quantity staff set on the base (R18a, R23)', function () {
    $bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, false, '2');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $bread, '1');
    ($this->settle)();
    $order = $this->world->orders->ofHousehold($this->cycleId, CONTAINER_H);
    $this->world->baskets->setActual($order->id, $bread, '3');
    $this->world->baskets->validate($order->id);

    expect(($this->world->getContainerCount)($this->world->manager, $this->cycleId)->total)->toBe(6);
});

it('derives unsent not-delivered marks and restores counts when the cause clears (R3b, R6c, R18a)', function () {
    $bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, false, '1');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $bread, '2');
    $count = fn () => ($this->world->getContainerCount)($this->world->manager, $this->cycleId)->total;
    $baker = Actor::producer('00000000-0000-7000-8000-0000000000b3', OrderingWorld::BAKER);

    expect($count())->toBe(2);
    $this->world->catalog->deactivate($bread);
    expect($count())->toBe(0);
    $this->world->catalog->reactivate($bread);
    expect($count())->toBe(2);

    ($this->world->setProductAvailability)($baker, $this->cycleId, $bread, false);
    expect($count())->toBe(0);
    ($this->world->setProductAvailability)($baker, $this->cycleId, $bread, true);
    expect($count())->toBe(2);

    ($this->world->stopParticipating)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    expect($count())->toBe(0);
    ($this->world->participateAgain)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    expect($count())->toBe(2);
});

it('keeps sent marks authoritative after later catalog changes (R3b, R18a, D9)', function () {
    $delivered = $this->world->catalog->offer(OrderingWorld::BAKER, 'Delivered', 'loaf', 300, false, '1');
    $marked = $this->world->catalog->offer(OrderingWorld::BAKER, 'Marked', 'loaf', 300, false, '2');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $delivered, '2');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $marked, '2');
    $this->world->catalog->deactivate($marked);
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    $this->world->catalog->reactivate($marked);
    $this->world->catalog->deactivate($delivered);

    expect(($this->world->getContainerCount)($this->world->manager, $this->cycleId)->total)->toBe(2);
});

it('shows each household its accumulated container balance beside the cycle count, negative included (R18a, S92)', function () {
    $honey = $this->world->catalog->offer(OrderingWorld::BAKER, 'Honey', 'jar', 600, false, '1');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $honey, '2');
    ($this->world->setOrderLine)($this->k, $this->cycleId, $honey, '1');
    $this->world->baskets->balances = [CONTAINER_H => 5, CONTAINER_K => -2];

    $rows = ($this->world->getContainerCount)($this->world->manager, $this->cycleId)->households;

    expect(array_map(fn ($row) => [$row->householdName, $row->count, $row->balance], $rows))
        ->toBe([['Household H', 2, 5], ['Household K', 1, -2]]);
});
