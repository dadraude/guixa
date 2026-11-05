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
        public array $collectionEvents, public bool $pendingCollection, public BasketActions $can,
        public string $status, public bool $settled, public bool $collected, public bool $weekly, public bool $draft,
        public array $weeklyLines = [], public string $kind = 'stock', public bool $late = false) {}

    public static function from(Basket $basket, bool $forStaff = true): self
    {
        return new self($basket->id, $basket->householdId, $basket->householdName, $basket->state->value,
            array_map(fn ($line) => BasketLineData::from($line, $basket->effectiveQuantityText($line), $basket->effectiveAmountCents($line)),
                array_values(array_filter($basket->activeLines(), fn ($line) => $line->kind->isStock()))),
            array_map(BasketReturnLineData::from(...), array_values(array_filter($basket->activeLines(), fn ($line) => $line->kind->isReturn()))),
            $basket->validatedTotalCents ?? $basket->totalCents(), $basket->createdAt,
            $basket->validatedAt, $basket->collectedAt, $basket->effectiveTotalCents(),
            array_map(BasketAdjustmentData::from(...), $basket->adjustments),
            array_map(BasketCollectionEventData::from(...), $basket->collectionEvents),
            $basket->isPendingCollection(),
            $forStaff ? BasketActions::forStaff($basket) : BasketActions::forMember($basket),
            $basket->isGoodsReversed() ? 'reversed' : $basket->state->value,
            $basket->state->isSettled(), $basket->state->isCollected(), $basket->isWeekly(), $basket->state->isDraft(),
            array_map(fn ($line) => BasketWeeklyLineData::from($line, $basket->effectiveQuantityText($line), $basket->effectiveAmountCents($line)),
                array_values(array_filter($basket->activeLines(), fn ($line) => $line->kind->isWeekly()))),
            $basket->kind->value, $basket->late);
    }
}
