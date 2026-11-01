<?php

namespace App\Http;

use Guixa\Sales\Application\ActiveHouseholdData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketHouseholdRow
{
    public function __construct(public string $id, public string $name) {}

    public static function from(ActiveHouseholdData $household): self
    {
        return new self($household->id, $household->name);
    }
}
