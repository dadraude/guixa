<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\OrderLine;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;
use Guixa\Ordering\Domain\WeeklyBasketView;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;
use Illuminate\Support\Facades\Gate;

/** Containers each household takes from Sales' effective weekly quantities, or its allocation before settlement (R18a). */
final class GetContainerCount
{
    private readonly NotDeliveredMarks $marks;

    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly Households $households,
        ProductCatalog $catalog,
        ProductAvailabilityRepository $availability,
        private readonly WeeklyBasketView $baskets,
    ) {
        $this->marks = new NotDeliveredMarks($catalog, $availability, $orders);
    }

    public function __invoke(Actor $actor, string $cycleId): ContainerCountData
    {
        Gate::forUser($actor)->authorize('manage');
        $cycle = $this->cycles->get($cycleId) ?? throw CycleNotFound::withId($cycleId);

        if ($cycle->isCancelled()) {
            return new ContainerCountData($cycleId, 0, []);
        }

        $orders = $this->orders->ofCycle($cycleId);
        $baskets = $this->baskets->snapshot($cycleId);
        $ids = array_values(array_unique(array_map(fn ($order) => $order->householdId, $orders)));
        $names = $this->households->find($ids);
        $rows = [];
        $total = 0;

        foreach ($orders as $order) {
            $count = 0;
            $marked = $this->marks->of($cycle, $order);

            foreach ($order->lines() as $line) {
                if ($line->dropped || isset($marked[$line->productId]) || $line->containersPerUnit === null) {
                    continue;
                }

                $count += $this->roundedContainers($this->taken($baskets->effective($order->householdId, $line->productId), $line), $line->containersPerUnit);
            }

            $rows[] = new HouseholdContainerCountData($order->householdId, $names[$order->householdId]->name ?? $order->householdId, $count);
            $total += $count;
        }

        usort($rows, fn (HouseholdContainerCountData $a, HouseholdContainerCountData $b) => [$a->householdName, $a->householdId] <=> [$b->householdName, $b->householdId]);

        return new ContainerCountData($cycleId, $total, $rows);
    }

    /** What the household takes: Sales' effective quantity once its base exists, the allocation before. */
    private function taken(?int $effective, OrderLine $line): Quantity
    {
        return $effective === null ? $line->allocated : Quantity::ofThousandths($effective);
    }

    private function roundedContainers(Quantity $quantity, Quantity $containersPerUnit): int
    {
        $wholeQuantity = intdiv($quantity->thousandths, 1000);
        $fractionalQuantity = $quantity->thousandths % 1000;
        $wholeRate = intdiv($containersPerUnit->thousandths, 1000);
        $fractionalRate = $containersPerUnit->thousandths % 1000;
        $fractionalProduct = ($wholeQuantity * $fractionalRate + $fractionalQuantity * $wholeRate) * 1000
            + $fractionalQuantity * $fractionalRate;

        return $wholeQuantity * $wholeRate + intdiv($fractionalProduct + 999999, 1000000);
    }
}
