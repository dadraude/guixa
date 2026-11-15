<?php

namespace Guixa\Membership\Infrastructure\Sales;

use Guixa\Membership\Domain\HouseholdBaskets;
use Guixa\Membership\Domain\ValueObject\OpenHouseholdBasket;
use Guixa\Sales\Application\OpenBasketData;
use Guixa\Sales\Application\ReadOpenHouseholdBaskets;

final class SalesHouseholdBaskets implements HouseholdBaskets
{
    public function __construct(private readonly ReadOpenHouseholdBaskets $readOpenBaskets) {}

    public function openFor(string $householdId): array
    {
        return array_map(
            fn (OpenBasketData $basket) => new OpenHouseholdBasket($basket->basketId, $basket->status, $basket->date),
            ($this->readOpenBaskets)($householdId),
        );
    }
}
