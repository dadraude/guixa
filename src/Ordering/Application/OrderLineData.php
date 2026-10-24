<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\OrderLine;

/** A line as a member sees it: the snapshot taken when it was last changed (R10). */
final readonly class OrderLineData
{
    public function __construct(
        public string $productId,
        public string $producerId,
        public string $name,
        public string $unit,
        public int $unitPriceCents,
        public bool $variable,
        public ?string $containersPerUnit,
        public string $quantity,
        public string $addedBy,
        public bool $notDelivered,
        public ?string $allocated = null,
        public ?string $delivered = null,
        public bool $adjusted = false,
        public ?int $amountCents = null,
    ) {}

    /** @param  bool  $notDelivered  the line carries the *will not be delivered* mark (R3b) */
    public static function of(OrderLine $line, bool $notDelivered): self
    {
        return new self(
            $line->productId,
            $line->producerId,
            $line->name,
            $line->unit,
            $line->unitPrice->cents,
            $line->variable,
            $line->containersPerUnit?->toString(),
            $line->ordered->toString(),
            $line->addedBy,
            $notDelivered,
        );
    }

    /** A line of a settled cycle's pickup: what was ordered, allocated and delivered, and what the delivered quantity costs (R19, R23a). */
    public static function ofPickup(OrderLine $line): self
    {
        return new self(
            $line->productId,
            $line->producerId,
            $line->name,
            $line->unit,
            $line->unitPrice->cents,
            $line->variable,
            $line->containersPerUnit?->toString(),
            $line->ordered->toString(),
            $line->addedBy,
            false,
            $line->allocated->toString(),
            $line->delivered()->toString(),
            $line->isAdjusted(),
            $line->amount()->cents,
        );
    }
}
