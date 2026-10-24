<?php

namespace App\Http;

use Guixa\Membership\Application\HouseholdSummary;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A household to pick in a form. */
#[TypeScript]
final readonly class HouseholdOptionRow
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function from(HouseholdSummary $household): self
    {
        return new self($household->id, $household->name);
    }
}
