<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderLine;
use Guixa\Ordering\Domain\ReturnLine;
use Guixa\Ordering\Domain\StockLine;

/** A household's pickup: its lines with what is delivered and what they cost, and the total so far (R19, R21, R23a). */
final readonly class PickupData
{
    /**
     * @param  list<OrderLineData>  $lines  in the order they were added; dropped lines are left out
     * @param  list<PickupStockLineData>  $stockLines  in the order they were added (R20)
     * @param  list<PickupReturnLineData>  $returnLines  in the order they were added (R20a)
     * @param  ?string  $householdName  null when the household reads its own pickup
     */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public string $householdId,
        public ?string $householdName,
        public string $status,
        public array $lines,
        public int $totalCents,
        public array $stockLines = [],
        public array $returnLines = [],
    ) {}

    public static function of(Order $order, string $pickupDate, ?string $householdName): self
    {
        return new self(
            $order->cycleId,
            $pickupDate,
            $order->householdId,
            $householdName,
            $order->pickup()->value,
            array_map(fn (OrderLine $line) => OrderLineData::ofPickup($line), $order->pickupLines()),
            $order->total()->cents,
            array_map(fn (StockLine $line) => PickupStockLineData::of($line), $order->stockLines()),
            array_map(fn (ReturnLine $line) => PickupReturnLineData::of($line), $order->returnLines()),
        );
    }
}
