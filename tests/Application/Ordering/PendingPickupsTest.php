<?php

use Guixa\Ordering\Application\ListPendingPickups;
use Guixa\Ordering\Application\ReadPendingPickups;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Shared\Application\Actor;
use Illuminate\Auth\Access\AuthorizationException;
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
    $list = new ListPendingPickups(new ReadPendingPickups($cycles, $orders));
    $world = new OrderingWorld;

    $pending = $list($world->manager, $household);

    expect($pending)->toHaveCount(1)
        ->and($pending[0]->cycleId)->toBe('cycle-1')
        ->and($pending[0]->pickupDate)->toBe('2026-10-28')
        ->and(fn () => $list(Actor::memberOf('member', $household), $household))->toThrow(AuthorizationException::class);
});

it('reads pending pickups for another context without an actor, ordered by pickup date (R35a)', function () {
    $cycles = new InMemoryCycleRepository;
    $orders = new InMemoryOrderRepository;
    $household = 'household-h';
    $cycles->add(Cycle::restore('cycle-late', new DateTimeImmutable('2026-11-11'), []));
    $cycles->add(Cycle::restore('cycle-early', new DateTimeImmutable('2026-10-28'), []));
    $orders->add(Order::restore('order-1', 'cycle-late', $household, [], PickupStatus::Pending));
    $orders->add(Order::restore('order-2', 'cycle-early', $household, [], PickupStatus::Pending));

    $pending = (new ReadPendingPickups($cycles, $orders))($household);

    expect(array_map(fn ($pickup) => $pickup->cycleId, $pending))->toBe(['cycle-early', 'cycle-late']);
});
