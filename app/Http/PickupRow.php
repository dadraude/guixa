<?php

namespace App\Http;

use Guixa\Ordering\Application\PickupData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A household's pickup. The manager's page adds the balance from Wallet (R21); a member's order page has no balance and no household name. */
#[TypeScript]
final readonly class PickupRow
{
    /**
     * @param  list<PickupLineRow>  $lines
     * @param  list<PickupStockLineRow>  $stockLines
     * @param  list<PickupReturnLineRow>  $returnLines
     * @param  list<PickupAdjustmentRow>  $adjustments
     */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public string $householdId,
        public string $householdName,
        public string $status,
        public array $lines,
        public int $totalCents,
        public ?int $balanceCents,
        public ?int $resultingBalanceCents,
        public array $stockLines = [],
        public array $returnLines = [],
        public array $adjustments = [],
        public ?int $originalChargeCents = null,
    ) {}

    public static function from(PickupData $pickup, int $balanceCents): self
    {
        return new self(
            $pickup->cycleId,
            $pickup->pickupDate,
            $pickup->householdId,
            $pickup->householdName ?? $pickup->householdId,
            $pickup->status,
            array_map(PickupLineRow::from(...), $pickup->lines),
            $pickup->totalCents,
            $balanceCents,
            $pickup->status === 'pending' ? $balanceCents - $pickup->totalCents : $balanceCents,
            array_map(PickupStockLineRow::from(...), $pickup->stockLines),
            array_map(PickupReturnLineRow::from(...), $pickup->returnLines),
            array_map(PickupAdjustmentRow::from(...), $pickup->adjustments),
            $pickup->originalChargeCents,
        );
    }

    /** The pickup a member sees in their own order (R23a). */
    public static function forMember(PickupData $pickup): self
    {
        return new self(
            $pickup->cycleId,
            $pickup->pickupDate,
            $pickup->householdId,
            '',
            $pickup->status,
            array_map(PickupLineRow::from(...), $pickup->lines),
            $pickup->totalCents,
            null,
            null,
            array_map(PickupStockLineRow::from(...), $pickup->stockLines),
            array_map(PickupReturnLineRow::from(...), $pickup->returnLines),
            array_map(PickupAdjustmentRow::from(...), $pickup->adjustments),
            $pickup->originalChargeCents,
        );
    }
}
