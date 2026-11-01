<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Error\PickupNotFound;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** What the manager sees of one household's pickup: lines, delivered quantities and the total (R19, R21). */
final class GetPickup
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly Households $households,
    ) {}

    public function __invoke(Actor $actor, string $cycleId, string $householdId): PickupData
    {
        Gate::forUser($actor)->authorize('manage');

        $cycle = $this->cycles->get($cycleId) ?? throw CycleNotFound::withId($cycleId);

        if (! $cycle->isSettled()) {
            throw CycleNotSettled::withId($cycleId);
        }

        $order = $this->orders->ofHousehold($cycleId, $householdId);

        if ($order === null || $order->pickup() === PickupStatus::None) {
            throw PickupNotFound::forHousehold($cycleId, $householdId);
        }

        $name = $this->households->find([$householdId])[$householdId]->name ?? $householdId;

        return PickupData::of($order, $cycle->pickupDate()->format('Y-m-d'), $name);
    }
}
