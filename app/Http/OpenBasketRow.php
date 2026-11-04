<?php

namespace App\Http;

use Guixa\Membership\Application\OpenHouseholdBasketData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class OpenBasketRow
{
    public function __construct(public string $basketId, public string $status, public string $date) {}

    public static function from(OpenHouseholdBasketData $basket): self
    {
        return new self($basket->basketId, $basket->status, $basket->date);
    }
}
