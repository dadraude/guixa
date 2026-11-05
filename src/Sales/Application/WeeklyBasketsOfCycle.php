<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;

/** Finds the weekly baskets of a cycle through its source orders: a basket stores its source order and no cycle. */
final class WeeklyBasketsOfCycle
{
    public function __construct(private readonly BasketRepository $baskets, private readonly WeeklySourceOrders $sourceOrders) {}

    /** @return list<Basket> every weekly basket, base and late, by id */
    public function __invoke(string $cycleId): array
    {
        return $this->baskets->weeklyOfSourceOrders($this->sourceOrders->ofCycle($cycleId));
    }
}
