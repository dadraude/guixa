<?php

namespace Guixa\Catalog\Domain;

/** What Catalog asks the cycles about a product (R1) and tells them about a producer (R3a). */
interface ProducerCycles
{
    /** Whether any household order has a line of the product in a cycle that is not settled. */
    public function hasUnsettledLines(string $productId): bool;

    /** Takes the producer out of every cycle where its order is not sent (R3a, R6c). */
    public function stopParticipatingWhereNotSent(string $producerId): void;
}
