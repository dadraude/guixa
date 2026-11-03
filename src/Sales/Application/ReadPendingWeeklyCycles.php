<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;

/** Internal query for Ordering: cycles where the household still has a weekly base to validate or to collect (R35a). */
final class ReadPendingWeeklyCycles
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /** @return list<string> cycle ids, without repeats */
    public function __invoke(string $householdId): array
    {
        $cycles = [];
        foreach ($this->baskets->listForHousehold($householdId) as $basket) {
            if (! $basket->isWeekly() || $basket->referenceCycleId === null) {
                continue;
            }
            $pending = $basket->state === 'draft' || ($basket->state === 'validated' && ! $basket->goodsFullyReversed());
            if ($pending) {
                $cycles[$basket->referenceCycleId] = true;
            }
        }

        return array_keys($cycles);
    }
}
