<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\Error\CycleNotFound;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderLine;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Quantity;

/** What the manager sees of a cycle: totals per producer and product, and the breakdown per household; a cancelled cycle has none (R13, S62). */
final class GetCycleSummary
{
    public function __construct(
        private readonly CycleRepository $cycles,
        private readonly OrderRepository $orders,
        private readonly Households $households,
    ) {}

    public function __invoke(Actor $actor, string $cycleId): CycleSummaryData
    {
        $actor->assertManager();

        $cycle = $this->cycles->get($cycleId) ?? throw CycleNotFound::withId($cycleId);

        if ($cycle->isCancelled()) {
            return new CycleSummaryData($cycle->id, $cycle->pickupDate()->format('Y-m-d'), [], cancelled: true);
        }

        $orders = array_values(array_filter(
            $this->orders->ofCycle($cycleId),
            fn (Order $order) => $order->orderedLines() !== [],
        ));
        $names = $this->householdNames($orders);

        return new CycleSummaryData(
            $cycle->id,
            $cycle->pickupDate()->format('Y-m-d'),
            $this->producerTotals($orders, $names),
            cancelled: false,
        );
    }

    /**
     * @param  list<Order>  $orders
     * @return array<string, string> household name by id
     */
    private function householdNames(array $orders): array
    {
        $ids = array_values(array_unique(array_map(fn (Order $order) => $order->householdId, $orders)));
        $known = $this->households->find($ids);

        return array_combine($ids, array_map(fn (string $id) => isset($known[$id]) ? $known[$id]->name : $id, $ids));
    }

    /**
     * @param  list<Order>  $orders
     * @param  array<string, string>  $names
     * @return list<ProducerTotalsData>
     */
    private function producerTotals(array $orders, array $names): array
    {
        /** @var array<string, array<string, list<array{line: OrderLine, householdId: string}>>> $grouped producer, then product */
        $grouped = [];

        foreach ($orders as $order) {
            foreach ($order->orderedLines() as $line) {
                $grouped[$line->producerId][$line->productId][] = ['line' => $line, 'householdId' => $order->householdId];
            }
        }

        ksort($grouped);

        return array_map(
            fn (string $producerId, array $products) => new ProducerTotalsData(
                $producerId,
                $this->productTotals($products, $names),
            ),
            array_keys($grouped),
            $grouped,
        );
    }

    /**
     * @param  array<string, list<array{line: OrderLine, householdId: string}>>  $products
     * @param  array<string, string>  $names
     * @return list<ProductTotalData>
     */
    private function productTotals(array $products, array $names): array
    {
        $totals = array_map(function (array $entries) use ($names) {
            $households = array_map(
                fn (array $entry) => new HouseholdQuantityData(
                    $entry['householdId'],
                    $names[$entry['householdId']],
                    $entry['line']->ordered->toString(),
                ),
                $entries,
            );
            usort($households, fn (HouseholdQuantityData $a, HouseholdQuantityData $b) => [$a->householdName, $a->householdId] <=> [$b->householdName, $b->householdId]);

            $thousandths = array_sum(array_map(fn (array $entry) => $entry['line']->ordered->thousandths, $entries));
            $line = $entries[0]['line'];

            return new ProductTotalData(
                $line->productId,
                $line->name,
                $line->unit,
                Quantity::ofThousandths($thousandths)->toString(),
                $households,
            );
        }, array_values($products));

        usort($totals, fn (ProductTotalData $a, ProductTotalData $b) => [$a->name, $a->productId] <=> [$b->name, $b->productId]);

        return $totals;
    }
}
