<?php

namespace Guixa\Catalog\Infrastructure\Ordering;

use Guixa\Catalog\Domain\ProducerCycles;
use Guixa\Ordering\Application\HasUnsettledLines;

/** Answers Catalog's questions about cycles through Ordering's use case (A4). */
final class OrderingProducerCycles implements ProducerCycles
{
    public function __construct(private readonly HasUnsettledLines $hasUnsettledLines) {}

    public function hasUnsettledLines(string $productId): bool
    {
        return ($this->hasUnsettledLines)($productId);
    }
}
