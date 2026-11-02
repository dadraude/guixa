<?php

namespace Guixa\Sales\Application;

use DateTimeImmutable;
use Guixa\Sales\Domain\Basket;

final readonly class BasketData
{
    /** @param list<BasketLineData> $stockLines
     * @param  list<BasketReturnLineData>  $returnLines
     * @param  list<BasketAdjustmentData>  $adjustments
     */
    public function __construct(public string $id, public string $householdId, public string $householdName,
        public string $state, public array $stockLines, public array $returnLines, public int $totalCents,
        public DateTimeImmutable $createdAt, public ?DateTimeImmutable $validatedAt,
        public ?DateTimeImmutable $collectedAt, public int $effectiveTotalCents, public array $adjustments) {}

    public static function from(Basket $basket): self
    {
        return new self($basket->id, $basket->householdId, $basket->householdName, $basket->state,
            array_map(BasketLineData::from(...), array_values(array_filter($basket->activeLines(), fn ($line) => $line->kind === 'stock'))),
            array_map(BasketReturnLineData::from(...), array_values(array_filter($basket->activeLines(), fn ($line) => $line->kind === 'return'))),
            $basket->validatedTotalCents ?? $basket->totalCents(), $basket->createdAt,
            $basket->validatedAt, $basket->collectedAt, $basket->effectiveTotalCents(),
            array_map(BasketAdjustmentData::from(...), $basket->adjustments));
    }
}
