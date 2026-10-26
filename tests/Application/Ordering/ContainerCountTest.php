<?php

use Guixa\Ordering\Application\CycleSettlement;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\ReceptionLoader;
use Guixa\Ordering\Domain\ValueObject\StockOffer;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
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
});

it('counts rounded snapshotted allocation and stock lines per household and cycle (R1, R10, R18a, R20, R25, S60)', function () {
    $yoghurt = $this->world->catalog->offer(OrderingWorld::BAKER, 'Yoghurt', 'kg', 400, true, '2');
    $honey = $this->world->catalog->offer(OrderingWorld::BAKER, 'Honey', 'jar', 600, false, '1');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $yoghurt, '1.300');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $honey, '3');
    ($this->world->setOrderLine)($this->k, $this->cycleId, $yoghurt, '0.500');
    $this->world->catalog->changeContainersPerUnit($yoghurt, '4');
    $stock = $this->world->stockProduct('Stock honey', 600, '5', containersPerUnit: '1');
    $loader = new ReceptionLoader($this->world->orders, $this->world->catalog, $this->world->availability, $this->world->receivedTotals);
    $transactions = new ImmediateTransactionRunner;
    $settlement = new CycleSettlement($this->world->orders, new NotDeliveredMarks($this->world->catalog, $this->world->availability, $this->world->orders));
    $validate = new ValidateReception($this->world->cycles, $transactions, $this->world->clock);
    $distribute = new MarkProducerDistributed($this->world->cycles, $loader, $settlement, $transactions, $this->world->clock);
    ($this->world->closeProducerNow)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->sendProducerOrder)($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    $validate($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    $distribute($this->world->manager, $this->cycleId, OrderingWorld::BAKER);
    ($this->world->addStockToPickup)($this->world->manager, $this->cycleId, CONTAINER_K, $stock, '2');

    $count = ($this->world->getContainerCount)($this->world->manager, $this->cycleId);

    expect($count->total)->toBe(9)
        ->and(array_map(fn ($row) => [$row->householdName, $row->count], $count->households))
        ->toBe([['Household H', 6], ['Household K', 3]]);
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
    $order = $this->world->orders->ofHousehold($this->cycleId, CONTAINER_H);
    $this->world->catalog->deactivate($marked);
    $order->drop([$dropped => true]);
    $order->adjustDelivered($bread, Quantity::fromString('1'));
    $this->world->orders->add($order);

    expect(($this->world->getContainerCount)($this->world->manager, $this->cycleId)->total)->toBe(1);
});

it('is manager-only, rejects unknown cycles and has no cancelled totals (R18a)', function () {
    expect(fn () => ($this->world->getContainerCount)($this->h, $this->cycleId))->toThrow(NotAllowed::class)
        ->and(fn () => ($this->world->getContainerCount)($this->world->manager, 'missing'))->toThrow(CycleNotFound::class);
    ($this->world->cancelCycle)($this->world->manager, $this->cycleId);
    expect(($this->world->getContainerCount)($this->world->manager, $this->cycleId)->total)->toBe(0);
});

it('includes a stock-only household and uses the latest done-pickup adjustment target (R18a, R20, R23)', function () {
    $bread = $this->world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 300, false, '2');
    ($this->world->setOrderLine)($this->h, $this->cycleId, $bread, '1');
    $order = $this->world->orders->ofHousehold($this->cycleId, CONTAINER_H);
    $order->drop([]);
    $order->complete($this->world->clock->now());
    $order->appendAdjustment($bread, Quantity::fromString('3'), $this->world->orders->nextId(), $this->world->clock->now(), $this->world->manager->accountId);
    $this->world->orders->add($order);
    $stock = $this->world->stockProduct('Stock honey', 600, '5', containersPerUnit: '1');
    $stockOrder = $this->world->orders->lockFor($this->cycleId, CONTAINER_K);
    $stockOrder->openPickup();
    $this->world->orders->add($stockOrder);
    // The stock line takes its own snapshot; a stock-only pickup has no ordered lines.
    $stockOrder = $this->world->orders->ofHousehold($this->cycleId, CONTAINER_K);
    $stockOrder->addStock(new StockOffer($stock, 'Stock honey', 'jar', Money::ofCents(600), false, Quantity::fromString('1'), true, Quantity::fromString('5')), Quantity::fromString('2'), $this->world->orders->nextId());
    $this->world->orders->add($stockOrder);

    $count = ($this->world->getContainerCount)($this->world->manager, $this->cycleId);
    expect($count->total)->toBe(8)
        ->and(array_map(fn ($row) => $row->count, $count->households))->toBe([6, 2]);
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
