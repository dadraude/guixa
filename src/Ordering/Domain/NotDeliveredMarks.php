<?php

namespace Guixa\Ordering\Domain;

/**
 * Which lines of an order carry the *will not be delivered* mark (R3b, R6c). Until a producer order is sent
 * the mark is derived on every read: the product is deactivated, unavailable in the cycle, or its producer
 * does not take part in the cycle.
 */
final class NotDeliveredMarks
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly ProductAvailabilityRepository $availability,
    ) {}

    /** @return array<string, true> product ids of the marked lines */
    public function of(Cycle $cycle, Order $order): array
    {
        $unavailable = array_flip($this->availability->unavailableIn($cycle->id));
        $marked = [];

        foreach ($order->lines() as $line) {
            if ($this->isMarked($line, $cycle, $unavailable)) {
                $marked[$line->productId] = true;
            }
        }

        return $marked;
    }

    /** @param  array<string, int>  $unavailable  product ids unavailable in the cycle, as keys */
    private function isMarked(OrderLine $line, Cycle $cycle, array $unavailable): bool
    {
        return ! $cycle->isParticipating($line->producerId)
            || isset($unavailable[$line->productId])
            || $this->catalog->orderable($line->productId) === null;
    }
}
