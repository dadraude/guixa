<?php

namespace App\Http;

use Guixa\Membership\Application\HouseholdSettlement;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class HouseholdSettlementRow
{
    /** @param list<OpenBasketRow> $openBaskets */
    public function __construct(
        public string $householdId,
        public string $householdName,
        public int $balanceCents,
        public array $openBaskets,
    ) {}

    public static function from(HouseholdSettlement $settlement): self
    {
        return new self(
            $settlement->householdId,
            $settlement->householdName,
            $settlement->balanceCents,
            array_map(OpenBasketRow::from(...), $settlement->openBaskets),
        );
    }
}
