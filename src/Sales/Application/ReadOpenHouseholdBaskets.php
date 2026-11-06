<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\CyclePickupDates;
use Guixa\Shared\Domain\Clock;

/** Internal query for Membership, which settles households its own manager-only use cases already authorised (R35a); it takes no actor. */
final class ReadOpenHouseholdBaskets
{
    public function __construct(
        private readonly BasketRepository $baskets,
        private readonly CyclePickupDates $pickupDates,
        private readonly WeeklySourceOrders $sourceOrders,
        private readonly Clock $clock,
    ) {}

    /** @return list<OpenBasketData> weekly, stock and return baskets still to validate or to collect, oldest date first; a weekly basket is dated by its cycle's pickup, any other by its creation */
    public function __invoke(string $householdId): array
    {
        $open = [
            ...array_filter($this->baskets->listForHousehold($householdId), fn (Basket $basket) => $basket->state->isDraft()),
            ...$this->baskets->pendingCollections($householdId),
        ];
        $cycles = $this->sourceOrders->cyclesOf(array_values(array_filter(array_map(
            fn (Basket $basket) => $basket->isWeekly() ? $basket->sourceOrderId : null,
            $open,
        ))));
        $pickupDates = $this->pickupDates->forCycles(array_values(array_unique($cycles)));
        $data = array_map(
            fn (Basket $basket) => new OpenBasketData(
                $basket->id,
                $basket->state->value,
                $this->dateOf($basket, $cycles, $pickupDates),
            ),
            $open,
        );

        usort($data, fn (OpenBasketData $a, OpenBasketData $b) => [$a->date, $a->basketId] <=> [$b->date, $b->basketId]);

        return $data;
    }

    /**
     * @param  array<string, string>  $cycles  cycle id by source order id
     * @param  array<string, string>  $pickupDates  Y-m-d by cycle id
     */
    private function dateOf(Basket $basket, array $cycles, array $pickupDates): string
    {
        $cycleId = $basket->isWeekly() ? ($cycles[(string) $basket->sourceOrderId] ?? null) : null;
        if ($cycleId !== null) {
            return $pickupDates[$cycleId] ?? $basket->createdAt->setTimezone($this->clock->timezone())->format('Y-m-d');
        }

        return $basket->createdAt->setTimezone($this->clock->timezone())->format('Y-m-d');
    }
}
