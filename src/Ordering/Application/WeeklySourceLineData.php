<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderLine;

/** One weekly order line as Sales snapshots it: what was ordered, what the reception allocated and the product at that moment. */
final readonly class WeeklySourceLineData
{
    public function __construct(
        public string $sourceLineId,
        public string $productId,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $variable,
        public ?string $containersPerUnit,
        public string $ordered,
        public string $allocated,
    ) {}

    /** A dropped line was never received: it keeps its ordered snapshot but has nothing allocated. */
    public static function of(OrderLine $line): self
    {
        return new self($line->id, $line->productId, $line->name, $line->unit, $line->unitPrice->cents, $line->variable,
            $line->containersPerUnit?->toString(), $line->ordered->toString(), $line->dropped ? '0' : $line->allocated->toString());
    }
}
