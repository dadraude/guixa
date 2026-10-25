<?php

namespace App\Http;

use Guixa\Membership\Application\PendingPickupData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class PendingPickupRow
{
    public function __construct(public string $cycleId, public string $pickupDate) {}

    public static function from(PendingPickupData $pickup): self
    {
        return new self($pickup->cycleId, $pickup->pickupDate);
    }
}
