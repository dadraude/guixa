<?php

namespace App\Http;

use Guixa\Ordering\Application\PickupSummaryData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One row of the pickups list: a household, where its pickup stands and what it costs so far, in cents. */
#[TypeScript]
final readonly class PickupSummaryRow
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public string $status,
        public int $totalCents,
    ) {}

    public static function from(PickupSummaryData $pickup): self
    {
        return new self($pickup->householdId, $pickup->householdName, $pickup->status, $pickup->totalCents);
    }
}
