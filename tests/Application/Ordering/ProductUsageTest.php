<?php

use Guixa\Ordering\Application\CheckProductUsage;
use Guixa\Ordering\Domain\ReceivedTotal;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Tests\Support\Ordering\OrderingWorld;

it('derives use from removed and cancelled order lines and zeroed reception records (R1b, S68, S69, S87)', function () {
    $world = new OrderingWorld('2026-10-22 10:00');
    $bread = $world->catalog->offer(OrderingWorld::BAKER, 'Bread', 'loaf', 350, variable: false);
    $cycle = ($world->openCycle)($world->manager, '2026-11-07', [OrderingWorld::BAKER]);
    $anna = Actor::memberOf('00000000-0000-7000-8000-0000000000a1', '00000000-0000-7000-8000-0000000000e1');
    $usage = new CheckProductUsage($world->orders, $world->receivedTotals);

    expect($usage($bread, false))->toBeFalse();
    ($world->setOrderLine)($anna, $cycle, $bread, '1');
    ($world->setOrderLine)($anna, $cycle, $bread, '0');
    expect($usage($bread, false))->toBeTrue();
    ($world->cancelCycle)($world->manager, $cycle);
    expect($usage($bread, false))->toBeTrue();

    $other = $world->catalog->offer(OrderingWorld::BAKER, 'Rye', 'loaf', 400, variable: false);
    $world->receivedTotals->set(new ReceivedTotal($cycle, OrderingWorld::BAKER, $other, Quantity::fromString('0')));
    expect($usage($other, false))->toBeTrue();
});
