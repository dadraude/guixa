<?php

namespace App\Http;

use Guixa\Sales\Application\BasketData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One row of the cycle's weekly baskets: a household, the state of its base or late weekly basket and what it costs, in cents. */
#[TypeScript]
final readonly class PickupSummaryRow
{
    public function __construct(
        public string $basketId,
        public string $householdId,
        public string $householdName,
        public string $status,
        public int $totalCents,
        public bool $late = false,
    ) {}

    public static function from(BasketData $basket): self
    {
        return new self($basket->id, $basket->householdId, $basket->householdName, $basket->state, $basket->effectiveTotalCents, $basket->late);
    }
}
