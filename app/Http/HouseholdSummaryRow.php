<?php

namespace App\Http;

use Guixa\Membership\Application\HouseholdSummary;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class HouseholdSummaryRow
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public int $memberCount,
    ) {}

    public static function from(HouseholdSummary $summary): self
    {
        return new self($summary->id, $summary->name, $summary->active, $summary->memberCount);
    }
}
