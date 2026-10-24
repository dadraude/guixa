<?php

namespace Guixa\Ordering\Domain;

use Guixa\Shared\Domain\Quantity;

interface ReceivedTotalRepository
{
    /** Stores the total, replacing the one the product had in the cycle. */
    public function set(ReceivedTotal $total): void;

    /** @return array<string, Quantity> the producer's recorded totals in the cycle, by product id */
    public function ofProducer(string $cycleId, string $producerId): array;
}
