<?php

namespace Guixa\Ordering\Domain;

/**
 * Which lines of an order carry the *will not be delivered* mark (R3b, R6c). While a producer order is not sent the
 * mark is derived on every read: the product is deactivated, unavailable in the cycle, or its producer does not take
 * part in the cycle. `send` stores it (D9), and from then on the stored mark is what counts.
 */
final class NotDeliveredMarks
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly ProductAvailabilityRepository $availability,
        private readonly OrderRepository $orders,
    ) {}

    /**
     * The marks to show: stored for the lines of a sent producer order, derived for the others.
     *
     * @return array<string, true> product ids of the marked lines
     */
    public function of(Cycle $cycle, Order $order): array
    {
        $stored = array_flip($this->orders->notDeliveredProductIds($order->id));
        $derived = $this->derivedOf($cycle, $order);
        $marked = [];

        foreach ($order->lines() as $line) {
            $isMarked = $cycle->isSent($line->producerId) ? isset($stored[$line->productId]) : isset($derived[$line->productId]);

            if ($isMarked) {
                $marked[$line->productId] = true;
            }
        }

        return $marked;
    }

    /**
     * The marks as the catalog, availability and participation say now, whatever is stored. `send` uses it to decide
     * what to store.
     *
     * @return array<string, true> product ids of the marked lines
     */
    public function derivedOf(Cycle $cycle, Order $order): array
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

    /**
     * The derived marks of every given order, which sending, the scheduler and the manual-send read share.
     *
     * @param  list<Order>  $orders
     * @return array<string, array<string, true>> derived marks per order id
     */
    public function derivedOfAll(Cycle $cycle, array $orders): array
    {
        $marked = [];

        foreach ($orders as $order) {
            $marked[$order->id] = $this->derivedOf($cycle, $order);
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
