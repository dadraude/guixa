<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\AccountNames;
use Guixa\Sales\Domain\Basket;

/** Resolves, in one batch, the names of everyone who adjusted a basket or recorded its collection (R23a). */
final readonly class BasketAuthorNames
{
    public function __construct(private AccountNames $accountNames) {}

    /** @return array<string, string> name by account id */
    public function of(Basket $basket): array
    {
        $authorIds = [
            ...array_map(fn ($adjustment) => $adjustment->recordedBy, $basket->adjustments),
            ...array_map(fn ($event) => $event->recordedBy, $basket->collectionEvents),
        ];

        return $this->accountNames->forAccounts(array_values(array_unique($authorIds)));
    }
}
