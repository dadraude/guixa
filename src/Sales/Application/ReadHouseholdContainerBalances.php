<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\ContainerBalance;

/** Internal query for Ordering's container summary: the accumulated container balance of each household (R18a). */
final class ReadHouseholdContainerBalances
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /**
     * @param  list<string>  $householdIds
     * @return array<string, int> balance by household id
     */
    public function __invoke(array $householdIds): array
    {
        $byHousehold = array_fill_keys($householdIds, []);
        foreach ($this->baskets->settledForHouseholds($householdIds) as $basket) {
            $byHousehold[$basket->householdId][] = $basket;
        }

        return array_map(ContainerBalance::of(...), $byHousehold);
    }
}
