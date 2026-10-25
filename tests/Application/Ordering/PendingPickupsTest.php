<?php

use Guixa\Ordering\Application\ListPendingPickups;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;
use Tests\Support\InMemory\InMemoryCycleRepository;
use Tests\Support\InMemory\InMemoryOrderRepository;
use Tests\Support\Ordering\OrderingWorld;

it('lists only pending pickups of the requested household across cycles (R35a, S58)', function () {
    $cycles = new InMemoryCycleRepository;
    $orders = new InMemoryOrderRepository;
    $household = 'household-h';
    foreach (['2026-10-28', '2026-11-04', '2026-11-11'] as $index => $date) {
        $cycleId = 'cycle-'.($index + 1);
        $cycles->add(Cycle::restore($cycleId, new DateTimeImmutable($date), []));
    }
    $orders->add(Order::restore('order-1', 'cycle-1', $household, [], PickupStatus::Pending));
    $orders->add(Order::restore('order-2', 'cycle-2', $household, [], PickupStatus::Completed));
    $orders->add(Order::restore('order-3', 'cycle-3', 'other-household', [], PickupStatus::Pending));
    $list = new ListPendingPickups($cycles, $orders);
    $world = new OrderingWorld;

    $pending = $list($world->manager, $household);

    expect($pending)->toHaveCount(1)
        ->and($pending[0]->cycleId)->toBe('cycle-1')
        ->and($pending[0]->pickupDate)->toBe('2026-10-28')
        ->and(fn () => $list(new Actor('member', [Role::Member], $household), $household))->toThrow(NotAllowed::class);
});
