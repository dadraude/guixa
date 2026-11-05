<?php

namespace App\Http;

use Guixa\Sales\Application\BasketData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketRow
{
    /** @param list<BasketStockLineRow> $stockLines
     * @param  list<BasketReturnLineRow>  $returnLines
     * @param  list<BasketAdjustmentRow>  $adjustments
     * @param  list<BasketCollectionEventRow>  $collectionEvents
     * @param  list<BasketWeeklyLineRow>  $weeklyLines
     */
    public function __construct(
        public string $id,
        public string $householdId,
        public string $householdName,
        public string $state,
        public array $stockLines,
        public array $returnLines,
        public int $totalCents,
        public int $balanceCents,
        public ?int $projectedBalanceCents,
        public ?string $validatedAt,
        public ?string $collectedAt,
        public int $effectiveTotalCents,
        public array $adjustments,
        public array $collectionEvents,
        public bool $pendingCollection,
        public BasketActionsRow $can,
        public string $status,
        public bool $settled,
        public bool $collected,
        public bool $weekly,
        public array $weeklyLines = [],
        public string $kind = 'stock',
        public bool $late = false,
    ) {}

    public static function from(BasketData $basket, int $balanceCents): self
    {
        $total = $basket->totalCents;

        return new self($basket->id, $basket->householdId, $basket->householdName,
            $basket->state, array_map(BasketStockLineRow::from(...), $basket->stockLines),
            array_map(BasketReturnLineRow::from(...), $basket->returnLines),
            $total, $balanceCents, $basket->draft ? $balanceCents - $total : null,
            $basket->validatedAt?->format(DATE_ATOM), $basket->collectedAt?->format(DATE_ATOM),
            $basket->effectiveTotalCents, array_map(BasketAdjustmentRow::from(...), $basket->adjustments),
            array_map(BasketCollectionEventRow::from(...), $basket->collectionEvents),
            $basket->pendingCollection, BasketActionsRow::from($basket->can), $basket->status, $basket->settled,
            $basket->collected, $basket->weekly, array_map(BasketWeeklyLineRow::from(...), $basket->weeklyLines),
            $basket->kind, $basket->late);
    }
}
