<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Quantity;

/** Turns a source order's lines into weekly basket lines: ordered and allocated snapshots, the actual quantity starting at the allocation. */
final class BuildWeeklyBasketLines
{
    public function __construct(private readonly BasketRepository $baskets, private readonly Clock $clock) {}

    /** @return list<BasketLine> */
    public function __invoke(WeeklyBaseData $source, string $basketId): array
    {
        $now = $this->clock->now();

        return array_map(fn (WeeklyBaseLineData $line) => new BasketLine(
            $this->baskets->nextId(), $basketId, $line->productId, $line->name, $line->unit, $line->priceCents, $line->variable,
            $line->containersPerUnit, Quantity::fromString($line->allocated), $now,
            kind: 'weekly', sourceOrderLineId: $line->sourceLineId,
            orderedQuantity: Quantity::fromString($line->ordered), allocatedQuantity: Quantity::fromString($line->allocated),
        ), $source->lines);
    }
}
