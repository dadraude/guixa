<?php

namespace Guixa\Ordering\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Quantity;

/** How a producer is told its order was sent (R12a). */
interface ProducerNotifier
{
    /** Whether the producer has an account to notify. */
    public function canNotify(string $producerId): bool;

    /**
     * Tells the producer the total quantity per product it has to deliver, without household names.
     *
     * @param  list<array{name: string, unit: string, quantity: Quantity}>  $totals
     */
    public function notify(string $producerId, DateTimeImmutable $pickupDate, array $totals): void;
}
