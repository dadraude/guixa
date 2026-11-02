<?php

namespace App\Http;

use DateTimeZone;
use Guixa\Sales\Application\BasketData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketSummaryRow
{
    public function __construct(public string $id, public string $householdName, public string $state,
        public int $totalCents, public string $createdAt, public bool $pendingCollection) {}

    public static function from(BasketData $basket): self
    {
        return new self($basket->id, $basket->householdName, $basket->state,
            $basket->totalCents,
            $basket->createdAt->setTimezone(new DateTimeZone(config('guixa.timezone')))->format('Y-m-d H:i'), $basket->pendingCollection);
    }
}
