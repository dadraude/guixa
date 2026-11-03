<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;

/** Internal query for Ordering: every weekly basket, base or late, attributed to a cycle with its state and effective quantities. */
final class ReadWeeklyBasketStates
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /** @return list<WeeklyBasketStateData> */
    public function __invoke(string $cycleId): array
    {
        return array_map(function (Basket $basket) {
            $products = [];
            foreach ($basket->activeLines() as $line) {
                if ($line->kind === 'weekly') {
                    $products[(string) $line->productId] = true;
                }
            }
            foreach ($basket->adjustments as $adjustment) {
                foreach ($adjustment->lines as $line) {
                    $products[$line->productId] = true;
                }
            }

            return new WeeklyBasketStateData($basket->householdId, $basket->state,
                array_map(fn (string $productId) => $basket->effectiveQuantity($productId), array_combine(array_keys($products), array_keys($products))),
                $basket->id, $basket->late);
        }, $this->baskets->weeklyOfCycle($cycleId));
    }
}
