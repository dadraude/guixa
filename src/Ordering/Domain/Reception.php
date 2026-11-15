<?php

namespace Guixa\Ordering\Domain;

use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Shared\Domain\Quantity;

/**
 * A producer's reception in a cycle, read from its orders (R14, R17): one entry per product sent or added, with the
 * lines marked *will not be delivered* left out. Not an aggregate: the orders and the received totals are the state.
 */
final readonly class Reception
{
    /** @param  list<ReceptionProduct>  $products  sorted by name */
    private function __construct(public array $products) {}

    /**
     * @param  list<Order>  $orders  the cycle's orders
     * @param  array<string, array<string, true>>  $marksByOrder  marked product ids per order id (`NotDeliveredMarks::of`)
     * @param  array<string, Quantity>  $receivedTotals  recorded totals by product id; a product without one was received in full
     * @param  array<string, OrderableProduct>  $catalog  the producer's products by id, deactivated ones included, for products added with no line
     * @param  WeeklyBasketSnapshot  $baskets  Sales' effective quantities and validated bases: what households really take and which are frozen
     */
    public static function of(string $producerId, array $orders, array $marksByOrder, array $receivedTotals, array $catalog, WeeklyBasketSnapshot $baskets = new WeeklyBasketSnapshot): self
    {
        /** @var array<string, list<array{order: Order, line: OrderLine}>> $linesByProduct */
        $linesByProduct = [];

        foreach ($orders as $order) {
            foreach ($order->lines() as $line) {
                if ($line->producerId === $producerId && ! isset($marksByOrder[$order->id][$line->productId])) {
                    $linesByProduct[$line->productId][] = ['order' => $order, 'line' => $line];
                }
            }
        }

        $products = [];

        foreach (array_unique([...array_keys($linesByProduct), ...array_keys($receivedTotals)]) as $productId) {
            $product = self::productOf($productId, $linesByProduct[$productId] ?? [], $receivedTotals[$productId] ?? null, $catalog[$productId] ?? null, $baskets);

            if ($product !== null) {
                $products[] = $product;
            }
        }

        usort($products, fn (ReceptionProduct $a, ReceptionProduct $b) => [$a->name, $a->productId] <=> [$b->name, $b->productId]);

        return new self($products);
    }

    /** Every product received exactly what it was allocated, ignoring what pickups adjusted: what a producer needs to be distributed (R17, R18, R19). */
    public function balancedForDistribution(): bool
    {
        foreach ($this->products as $product) {
            if (! $product->balancedForDistribution()) {
                return false;
            }
        }

        return true;
    }

    public function product(string $productId): ?ReceptionProduct
    {
        foreach ($this->products as $product) {
            if ($product->productId === $productId) {
                return $product;
            }
        }

        return null;
    }

    /**
     * A product without a line or a recorded total is not in the reception; one Catalog does not know has no name to show, so it is skipped.
     *
     * @param  list<array{order: Order, line: OrderLine}>  $entries
     */
    private static function productOf(string $productId, array $entries, ?Quantity $recorded, ?OrderableProduct $catalogProduct, WeeklyBasketSnapshot $baskets): ?ReceptionProduct
    {
        $lines = array_map(fn (array $entry) => new ReceptionLine(
            $entry['order']->householdId,
            $entry['line']->id,
            $entry['line']->ordered,
            $entry['line']->allocated,
            self::actual($baskets, $entry['order']->householdId, $productId, $entry['line']),
            $entry['line']->allocatedByHand,
            $entry['line']->createdAt,
        ), $entries);

        $snapshot = $entries[0]['line'] ?? null;
        $name = $snapshot->name ?? $catalogProduct->name ?? null;

        if ($name === null) {
            return null;
        }

        $orderedInFull = Quantity::ofThousandths(array_sum(array_map(fn (ReceptionLine $line) => $line->ordered->thousandths, $lines)));

        return new ReceptionProduct(
            $productId,
            $name,
            $snapshot->unit ?? $catalogProduct->unit,
            $snapshot->variable ?? $catalogProduct->variable,
            $recorded ?? $orderedInFull,
            $lines,
        );
    }

    /** What Sales says the household takes; before its base exists the allocation is the actual quantity. */
    private static function actual(WeeklyBasketSnapshot $baskets, string $householdId, string $productId, OrderLine $line): Quantity
    {
        $thousandths = $baskets->effective($householdId, $productId);

        return $thousandths === null ? $line->allocated : Quantity::ofThousandths($thousandths);
    }
}
