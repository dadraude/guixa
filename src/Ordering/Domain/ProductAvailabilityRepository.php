<?php

namespace Guixa\Ordering\Domain;

/** The products a producer marked unavailable in a cycle (R3c). Everything else is available. */
interface ProductAvailabilityRepository
{
    /** Marking a product that is already unavailable changes nothing. */
    public function markUnavailable(string $cycleId, string $productId): void;

    /** Marking a product that is available changes nothing. */
    public function markAvailable(string $cycleId, string $productId): void;

    /** @return list<string> ids of the products unavailable in the cycle */
    public function unavailableIn(string $cycleId): array;
}
