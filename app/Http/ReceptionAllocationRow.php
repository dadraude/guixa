<?php

namespace App\Http;

use Guixa\Ordering\Application\ReceptionAllocationData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** What one household ordered of a product and what it is allocated of the reception. */
#[TypeScript]
final readonly class ReceptionAllocationRow
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public string $ordered,
        public string $allocated,
        public bool $byHand,
    ) {}

    public static function from(ReceptionAllocationData $allocation): self
    {
        return new self($allocation->householdId, $allocation->householdName, $allocation->ordered, $allocation->allocated, $allocation->byHand);
    }
}
