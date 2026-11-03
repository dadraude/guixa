<?php

namespace Guixa\Sales\Application;

use DateTimeImmutable;
use Guixa\Sales\Domain\Basket;

final readonly class BasketData
{
    /** @param list<BasketLineData> $stockLines
     * @param  list<BasketWeeklyLineData>  $weeklyLines
     * @param  list<BasketReturnLineData>  $returnLines
     * @param  list<BasketAdjustmentData>  $adjustments
     * @param  list<BasketCollectionEventData>  $collectionEvents
     */
    public function __construct(public string $id, public string $householdId, public string $householdName,
        public string $state, public array $stockLines, public array $returnLines, public int $totalCents,
        public DateTimeImmutable $createdAt, public ?DateTimeImmutable $validatedAt,
        public ?DateTimeImmutable $collectedAt, public int $effectiveTotalCents, public array $adjustments,
        public array $collectionEvents, public bool $pendingCollection,
        public array $weeklyLines = [], public string $kind = 'stock', public ?string $referenceCycleId = null, public bool $late = false) {}

    public static function from(Basket $basket): self
    {
        return new self($basket->id, $basket->householdId, $basket->householdName, $basket->state,
            array_map(fn ($line) => BasketLineData::from($line, $basket->effectiveQuantity($line->productId)),
                array_values(array_filter($basket->activeLines(), fn ($line) => $line->kind === 'stock'))),
            array_map(BasketReturnLineData::from(...), array_values(array_filter($basket->activeLines(), fn ($line) => $line->kind === 'return'))),
            $basket->validatedTotalCents ?? $basket->totalCents(), $basket->createdAt,
            $basket->validatedAt, $basket->collectedAt, $basket->effectiveTotalCents(),
            array_map(BasketAdjustmentData::from(...), $basket->adjustments),
            array_map(BasketCollectionEventData::from(...), $basket->collectionEvents),
            $basket->state === 'validated' && ! $basket->goodsFullyReversed(),
            array_map(fn ($line) => BasketWeeklyLineData::from($line, $basket->effectiveQuantity((string) $line->productId)),
                array_values(array_filter($basket->activeLines(), fn ($line) => $line->kind === 'weekly'))),
            $basket->kind, $basket->referenceCycleId, $basket->late);
    }
}
