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
            $balanceCents - $pickup->totalCents,
            array_map(PickupStockLineRow::from(...), $pickup->stockLines),
            array_map(PickupReturnLineRow::from(...), $pickup->returnLines),
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
        );
    }
}
