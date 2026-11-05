<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketRepository;

/** The one pending-collection query: validated baskets with effective goods left, whatever their cycle or its closure; a draft is preparation, not pending (R24, R24b). It takes no actor: each caller authorises its own audience. */
final class ReadPendingCollections
{
    public function __construct(private readonly BasketRepository $baskets) {}

    /** @return list<PendingCollectionData> ordered by validation instant, then id; one household's when given, otherwise all */
    public function __invoke(?string $householdId = null): array
    {
        return array_map(
            fn (Basket $basket) => new PendingCollectionData(
                $basket->id,
                $basket->householdId,
                $basket->householdName,
                $basket->isWeekly(),
                $basket->referenceCycleId,
                $basket->late,
                $basket->validatedAt,
                $basket->validatedTotalCents ?? $basket->totalCents(),
            ),
            $this->baskets->pendingCollections($householdId),
        );
    }
}
