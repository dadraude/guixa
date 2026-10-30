<?php

namespace Guixa\Sales\Application;

use DateTimeImmutable;
use Guixa\Sales\Domain\Basket;

final readonly class BasketData
{
    /** @param list<BasketLineData> $stockLines */
    public function __construct(public string $id, public string $householdId, public string $householdName,
        public string $state, public array $stockLines, public int $totalCents,
        public DateTimeImmutable $createdAt, public ?DateTimeImmutable $validatedAt,
        public ?DateTimeImmutable $collectedAt) {}

    public static function from(Basket $basket): self
    {
        return new self($basket->id, $basket->householdId, $basket->householdName, $basket->state,
            array_map(BasketLineData::from(...), $basket->activeLines()),
            $basket->validatedTotalCents ?? $basket->totalCents(), $basket->createdAt,
            $basket->validatedAt, $basket->collectedAt);
    }
}
