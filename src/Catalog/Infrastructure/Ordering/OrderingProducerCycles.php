<?php

namespace Guixa\Catalog\Infrastructure\Ordering;

use Guixa\Catalog\Domain\ProducerCycles;
use Guixa\Ordering\Application\HasUnsettledLines;
use Guixa\Ordering\Application\StopParticipatingWhereNotSent;

/** Answers Catalog's questions about cycles through Ordering's use case (A4). */
final class OrderingProducerCycles implements ProducerCycles
{
    public function __construct(
        private readonly HasUnsettledLines $hasUnsettledLines,
        private readonly StopParticipatingWhereNotSent $stopParticipatingWhereNotSent,
    ) {}

    public function hasUnsettledLines(string $productId): bool
    {
        return ($this->hasUnsettledLines)($productId);
    }

    public function stopParticipatingWhereNotSent(string $producerId): void
    {
        ($this->stopParticipatingWhereNotSent)($producerId);
    }
}
