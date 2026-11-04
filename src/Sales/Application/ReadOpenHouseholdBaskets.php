<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketRepository;

/** Internal query for Membership, which settles households its own manager-only use cases already authorised (R35a); it takes no actor. */
final class ReadOpenHouseholdBaskets
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /** @return list<OpenBasketData> weekly, stock and return baskets still to validate or to collect, oldest first */
    public function __invoke(string $householdId): array
    {
        $open = [];

        foreach ($this->baskets->listForHousehold($householdId) as $basket) {
            if ($basket->state->isDraft() || $basket->isPendingCollection()) {
                $open[] = new OpenBasketData($basket->id, $basket->state->value, $basket->createdAt->format('Y-m-d'));
            }
        }

        usort($open, fn (OpenBasketData $a, OpenBasketData $b) => [$a->date, $a->basketId] <=> [$b->date, $b->basketId]);

        return $open;
    }
}
