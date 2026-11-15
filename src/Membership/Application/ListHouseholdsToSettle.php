<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\HouseholdBalances;
use Guixa\Membership\Domain\HouseholdBaskets;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;
use Illuminate\Support\Facades\Gate;

final class ListHouseholdsToSettle
{
    public function __construct(
        private readonly HouseholdRepository $households,
        private readonly HouseholdBalances $balances,
        private readonly HouseholdBaskets $baskets,
    ) {}

    /** @return list<HouseholdSettlement> */
    public function __invoke(Actor $actor): array
    {
        Gate::forUser($actor)->authorize('manage');
        $rows = [];

        foreach ($this->households->list() as $household) {
            if ($household->isActive()) {
                continue;
            }

            $balance = $this->balances->balanceCents($household->id);
            $openBaskets = $this->baskets->openFor($household->id);

            if ($balance === 0 && $openBaskets === []) {
                continue;
            }

            $rows[] = new HouseholdSettlement($household->id, $household->name, $balance, $openBaskets);
        }

        usort($rows, fn (HouseholdSettlement $a, HouseholdSettlement $b) => [$a->householdName, $a->householdId] <=> [$b->householdName, $b->householdId]);

        return $rows;
    }
}
