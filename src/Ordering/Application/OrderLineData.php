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
    ) {}

    public static function of(OrderLine $line): self
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
        );
    }
}
