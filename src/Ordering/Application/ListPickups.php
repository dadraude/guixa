<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Error\CycleNotSettled;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

/** The pickups of a settled cycle, so the manager can open each one (R19). */
final class ListPickups
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly Households $households,
    ) {}

    /** @return list<PickupSummaryData> by household name */
    public function __invoke(Actor $actor, string $cycleId): array
    {
        Gate::forUser($actor)->authorize('manage');

        $cycle = $this->cycles->get($cycleId) ?? throw CycleNotFound::withId($cycleId);

        if (! $cycle->isSettled()) {
            throw CycleNotSettled::withId($cycleId);
        }

        $orders = array_values(array_filter(
            $this->orders->ofCycle($cycleId),
            fn (Order $order) => $order->pickup() !== PickupStatus::None,
        ));
        $ids = array_map(fn (Order $order) => $order->householdId, $orders);
        $known = $this->households->find($ids);

        $pickups = array_map(fn (Order $order) => new PickupSummaryData(
            $order->householdId,
            isset($known[$order->householdId]) ? $known[$order->householdId]->name : $order->householdId,
            $order->pickup()->value,
            $order->total()->cents,
        ), $orders);
        usort($pickups, fn (PickupSummaryData $a, PickupSummaryData $b) => [$a->householdName, $a->householdId] <=> [$b->householdName, $b->householdId]);

        return $pickups;
    }
}
