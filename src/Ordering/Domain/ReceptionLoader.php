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
        private readonly WeeklyBasketView $baskets,
    ) {
        $this->marks = new NotDeliveredMarks($catalog, $availability, $orders);
    }

    public function load(Cycle $cycle, string $producerId): LoadedReception
    {
        return $this->loadAll($cycle, [$producerId])[$producerId];
    }

    /**
     * Reads several producers' receptions of one cycle, sharing the orders, the marks and the baskets' snapshot, which
     * do not depend on the producer. `load` is this for one producer, so both always agree.
     *
     * @param  list<string>  $producerIds
     * @return array<string, LoadedReception> by producer id
     */
    public function loadAll(Cycle $cycle, array $producerIds): array
    {
        foreach ($producerIds as $producerId) {
            $cycle->assertSent($producerId);
        }

        $orders = $this->orders->ofCycle($cycle->id);
        $marksByOrder = $this->marks->ofAll($cycle, $orders);
        $snapshot = $this->baskets->snapshot($cycle->id);
        $loaded = [];

        foreach ($producerIds as $producerId) {
            $catalog = [];

            foreach ($this->catalog->allOf($producerId) as $product) {
                $catalog[$product->id] = $product;
            }

            $reception = Reception::of($producerId, $orders, $marksByOrder, $this->receivedTotals->ofProducer($cycle->id, $producerId), $catalog, $snapshot);
            $loaded[$producerId] = new LoadedReception($reception, $orders, $catalog, $marksByOrder, $snapshot);
        }

        return $loaded;
    }

    public function lockWeekly(string $productId): ?ValueObject\OrderableProduct
    {
        return $this->catalog->lockWeekly($productId);
    }
}
