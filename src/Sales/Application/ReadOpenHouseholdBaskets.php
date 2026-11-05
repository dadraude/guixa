<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\CyclePickupDates;

/** Internal query for Membership, which settles households its own manager-only use cases already authorised (R35a); it takes no actor. */
final class ReadOpenHouseholdBaskets
{
    public function __construct(
        private readonly BasketRepository $baskets,
        private readonly CyclePickupDates $pickupDates,
    ) {}

    /** @return list<OpenBasketData> weekly, stock and return baskets still to validate or to collect, oldest date first; a weekly basket is dated by its cycle's pickup, any other by its creation */
    public function __invoke(string $householdId): array
    {
        $open = [
            ...array_filter($this->baskets->listForHousehold($householdId), fn (Basket $basket) => $basket->state->isDraft()),
            ...$this->baskets->pendingCollections($householdId),
        ];
        $pickupDates = $this->pickupDates->forCycles(array_values(array_filter(array_map(
            fn (Basket $basket) => $basket->isWeekly() ? $basket->referenceCycleId : null,
            $open,
        ))));
        $data = array_map(
            fn (Basket $basket) => new OpenBasketData(
                $basket->id,
                $basket->state->value,
                $this->dateOf($basket, $pickupDates),
            ),
            $open,
        );

        usort($data, fn (OpenBasketData $a, OpenBasketData $b) => [$a->date, $a->basketId] <=> [$b->date, $b->basketId]);

        return $data;
    }

    /** @param array<string, string> $pickupDates */
    private function dateOf(Basket $basket, array $pickupDates): string
    {
        if ($basket->isWeekly() && $basket->referenceCycleId !== null) {
            return $pickupDates[$basket->referenceCycleId] ?? $basket->createdAt->format('Y-m-d');
        }

        return $basket->createdAt->format('Y-m-d');
    }
}
