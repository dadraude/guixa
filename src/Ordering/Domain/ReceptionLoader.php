<?php

namespace Guixa\Ordering\Domain;

/** Reads a producer's reception once its order is sent (R14); the reception use cases share it. */
final class ReceptionLoader
{
    private readonly NotDeliveredMarks $marks;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ProductCatalog $catalog,
        ProductAvailabilityRepository $availability,
        private readonly ReceivedTotalRepository $receivedTotals,
    ) {
        $this->marks = new NotDeliveredMarks($catalog, $availability, $orders);
    }

    public function load(Cycle $cycle, string $producerId): LoadedReception
    {
        $cycle->assertSent($producerId);

        $orders = $this->orders->ofCycle($cycle->id);
        $marksByOrder = [];

        foreach ($orders as $order) {
            $marksByOrder[$order->id] = $this->marks->of($cycle, $order);
        }

        $catalog = [];

        foreach ($this->catalog->allOf($producerId) as $product) {
            $catalog[$product->id] = $product;
        }

        $reception = Reception::of($producerId, $orders, $marksByOrder, $this->receivedTotals->ofProducer($cycle->id, $producerId), $catalog);

        return new LoadedReception($reception, $orders, $catalog, $marksByOrder);
    }

    public function lockWeekly(string $productId): ?ValueObject\OrderableProduct
    {
        return $this->catalog->lockWeekly($productId);
    }
}
