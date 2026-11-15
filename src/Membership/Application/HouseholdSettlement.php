<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\ValueObject\OpenHouseholdBasket;

final readonly class HouseholdSettlement
{
    /** @var list<OpenHouseholdBasketData> */
    public array $openBaskets;

    /** @param list<OpenHouseholdBasket> $openBaskets */
    public function __construct(
        public string $householdId,
        public string $householdName,
        public int $balanceCents,
        array $openBaskets,
    ) {
        $this->openBaskets = array_map(
            fn (OpenHouseholdBasket $basket) => new OpenHouseholdBasketData($basket->basketId, $basket->status, $basket->date),
            $openBaskets,
        );
    }
}
