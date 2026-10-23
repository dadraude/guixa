<?php

namespace App\Http;

use Guixa\Ordering\Application\HouseholdQuantityData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** What one household ordered of a product. */
#[TypeScript]
final readonly class HouseholdQuantityRow
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public string $quantity,
    ) {}

    public static function from(HouseholdQuantityData $household): self
    {
        return new self($household->householdId, $household->householdName, $household->quantity);
    }
}
