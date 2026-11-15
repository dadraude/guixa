<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Order;

/** A settled household order, the source of exactly one weekly base basket. */
final readonly class WeeklySourceData
{
    /** @param list<WeeklySourceLineData> $lines */
    public function __construct(
        public string $orderId,
        public string $cycleId,
        public string $householdId,
        public array $lines,
    ) {}

    public static function of(Order $order): self
    {
        return new self($order->id, $order->cycleId, $order->householdId,
            array_map(WeeklySourceLineData::of(...), $order->lines()));
    }
}
