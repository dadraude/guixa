<?php

namespace App\Http;

use Guixa\Membership\Application\HouseholdDetail;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The household whose wallet a manager is looking at. */
#[TypeScript]
final readonly class WalletHouseholdRow
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function from(HouseholdDetail $household): self
    {
        return new self($household->id, $household->name);
    }
}
