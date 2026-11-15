<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\NotDeliveredMarks;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ProductAvailabilityRepository;
use Guixa\Ordering\Domain\ProductCatalog;

/** Which producers have something to send, read outside the send lock for the manager's page; the use case checks again under the lock. */
final class SendableTotals
{
    private readonly NotDeliveredMarks $marks;

    public function __construct(
        private readonly OrderRepository $orders,
        ProductCatalog $catalog,
        ProductAvailabilityRepository $availability,
    ) {
        $this->marks = new NotDeliveredMarks($catalog, $availability, $orders);
    }

    /**
     * @param  list<string>  $producerIds
     * @return list<string> those with at least one line to send, from one read of the cycle's orders
     */
    public function __invoke(Cycle $cycle, array $producerIds): array
    {
        if ($producerIds === []) {
            return [];
        }

        $orders = $this->orders->ofCycle($cycle->id);
        $marked = $this->marks->derivedOfAll($cycle, $orders);

        return array_values(array_filter(
            $producerIds,
            fn (string $producerId) => $cycle->totalsToSend($producerId, $orders, $marked) !== [],
        ));
    }
}
